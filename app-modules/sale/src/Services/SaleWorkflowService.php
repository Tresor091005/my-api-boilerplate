<?php

declare(strict_types=1);

namespace Lahatre\Sale\Services;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Customer\Models\Customer;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Sale\Assertions\SaleAssertion;
use Lahatre\Sale\Data\SaleTransitionContext;
use Lahatre\Sale\Enums\SaleApprovalReason;
use Lahatre\Sale\Enums\SaleEvent;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Sale\Models\SaleTransition;
use Lahatre\Shared\StateMachine\StateMachine;
use Lahatre\Shared\StateMachine\StateMachineValue;
use Lahatre\Shared\StateMachine\TransitionFailure;
use Lahatre\Tax\Exceptions\TaxException;
use UnitEnum;

final readonly class SaleWorkflowService
{
    public function __construct(
        private StateMachine $saleStateMachine,
        private SaleAssertion $saleAssertion,
        private SaleCalculationService $saleCalculationService,
        private OrganizationInterface $organizationInterface,
    ) {}

    /**
     * Validates a draft into `approved`, or `waiting_approval` when the approval is not the actor's to give.
     *
     * @throws SaleException If the sale is not a draft, changed since it was read, its customer may no longer buy, or it is a credit refused (walk-in customer, no due date, a past due date).
     */
    public function validate(Sale $sale, int $version, bool $actorMayApprove): Sale
    {
        return $this->withLockedSale($sale, function (Sale $sale, string $organizationId) use ($version, $actorMayApprove): void {
            $this->assertGestureAllowed($sale, SaleEvent::Validate);
            $this->saleAssertion->assertVersionCurrent($sale, $version);
            $customer = $this->buyingCustomer($sale, $organizationId);
            $this->settleApproval($sale, $organizationId, $customer);

            $this->move($sale, SaleEvent::Validate, $this->context($sale, $actorMayApprove));
        });
    }

    /** @throws SaleException If the sale is not waiting for approval, or its customer may no longer buy. */
    public function approve(Sale $sale): Sale
    {
        return $this->withLockedSale($sale, function (Sale $sale, string $organizationId): void {
            $this->assertGestureAllowed($sale, SaleEvent::Approve);
            $this->buyingCustomer($sale, $organizationId);
            $this->move($sale, SaleEvent::Approve, $this->context($sale, true));
        });
    }

    /** @throws SaleException If the sale is not waiting for approval. */
    public function reject(Sale $sale, string $reason): Sale
    {
        return $this->withLockedSale($sale, function (Sale $sale) use ($reason): void {
            $this->move($sale, SaleEvent::Reject, $this->context($sale, true), $reason);
        });
    }

    /**
     * Takes a rejected or approved sale back to `draft`, losing the approval.
     *
     * @throws SaleException If the sale is neither rejected nor approved.
     */
    public function reopen(Sale $sale): Sale
    {
        return $this->withLockedSale($sale, function (Sale $sale): void {
            $this->backToDraft($sale, SaleEvent::Reopen);
        });
    }

    /** @throws SaleException If the sale is already confirmed, abandoned or cancelled. */
    public function abandon(Sale $sale, ?string $reason): Sale
    {
        return $this->withLockedSale($sale, function (Sale $sale) use ($reason): void {
            $this->move($sale, SaleEvent::Abandon, $this->context($sale, false), $reason);
        });
    }

    /**
     * Confirms an approved sale after recalculating it at its approved prices with today's catalog and taxes; it becomes `active`.
     * Creates no invoice and takes no stock out.
     *
     * @param  bool  $keepApprovedPrices  whether to sell at the approved prices when the catalog prices moved since
     *
     * @throws SaleException If the sale is not approved, a credit's due date has passed, something is no longer sellable, a catalog price moved and the approved prices are not kept, or its amounts changed.
     * @throws TaxException If the taxes of a line cannot be calculated.
     */
    public function confirm(Sale $sale, bool $keepApprovedPrices = false): Sale
    {
        return $this->withLockedSale($sale, function (Sale $sale, string $organizationId) use ($keepApprovedPrices): void {
            $this->assertGestureAllowed($sale, SaleEvent::Confirm);

            /** @var Collection<int, SaleLine> $storedLines */
            $storedLines = SaleLine::query()
                ->where('organization_id', $organizationId)
                ->where('sale_id', $sale->id)
                ->orderBy('position')
                ->get();
            try {
                $this->assertDueDateCurrent($sale, $organizationId);
                $current = $this->saleCalculationService->recalculate($sale, $storedLines, $organizationId);
            } catch (SaleException $obstacle) {
                throw SaleException::confirmationBlocked($sale->number, $obstacle);
            }

            if ($current['price_changes'] !== [] && !$keepApprovedPrices) {
                throw SaleException::pricesChangedSinceApproval($sale->number, $current['price_changes']);
            }

            $this->saleAssertion->assertAmountsUnchanged($sale, $storedLines, $current['lines']);

            $sale->confirmed_at = now();

            $this->move($sale, SaleEvent::Confirm, $this->context($sale, false));
        });
    }

    /**
     * Moves an active or completed sale to `cancelling`; only the system makes it `cancelled`.
     *
     * @throws SaleException If the sale is neither active nor completed.
     */
    public function cancel(Sale $sale, ?string $reason): Sale
    {
        return $this->withLockedSale($sale, function (Sale $sale) use ($reason): void {
            $this->move($sale, SaleEvent::Cancel, $this->context($sale, false), $reason);
        });
    }

    /**
     * Takes a sale being cancelled back to `active`; what was already undone stays undone.
     *
     * @throws SaleException If the sale is not being cancelled.
     */
    public function resume(Sale $sale, ?string $reason): Sale
    {
        return $this->withLockedSale($sale, function (Sale $sale) use ($reason): void {
            $this->move($sale, SaleEvent::Resume, $this->context($sale, false), $reason);
        });
    }

    /**
     * @param  Closure(Sale, string): mixed  $apply
     */
    private function withLockedSale(Sale $sale, Closure $apply): Sale
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($sale, $apply, $organizationId): Sale {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::query()
                ->where('organization_id', $organizationId)
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();
            $apply($lockedSale, $organizationId);

            return $lockedSale;
        });
    }

    private function backToDraft(Sale $sale, SaleEvent $event): void
    {
        $sale->approval_reasons = null;

        $this->move($sale, $event, $this->context($sale, false));
    }

    /**
     * Everything paid at once needs no approval; a lower share is a credit and needs a due date.
     *
     * @throws SaleException If the credit goes to the walk-in customer, or no due date applies from today on.
     */
    private function settleApproval(Sale $sale, string $organizationId, Customer $customer): void
    {
        $sale->approval_reasons = null;

        if ($sale->required_payment_percent >= Sale::FULL_PAYMENT_PERCENT) {
            return;
        }

        $this->saleAssertion->assertCreditAllowed($customer, $sale->required_payment_percent);
        $this->assertDueDateCurrent($sale, $organizationId, $customer, customerDatePassedCountsAsNone: true);

        $sale->approval_reasons = [SaleApprovalReason::CreditGranted->value];
    }

    /**
     * The due date is the one on the sale, else the customer's. When settling a draft, a past customer date counts as none.
     *
     * @throws SaleException If the date typed has passed, or none applies.
     */
    private function assertDueDateCurrent(Sale $sale, string $organizationId, ?Customer $customer = null, bool $customerDatePassedCountsAsNone = false): void
    {
        if ($sale->required_payment_percent >= Sale::FULL_PAYMENT_PERCENT) {
            return;
        }

        $today = $this->organizationInterface->today($organizationId)->toDateString();
        $typed = $sale->payment_due_date?->toDateString();

        if ($typed !== null && $typed < $today) {
            throw SaleException::paymentDueDatePassed($sale->number, $typed);
        }

        $customer ??= $this->customer($sale, $organizationId);
        $dueDate = $sale->dueDate($customer->payment_due_date)?->toDateString();

        if ($dueDate === null) {
            throw SaleException::paymentDueDateMissing($sale->number, $sale->required_payment_percent);
        }

        if ($dueDate < $today) {
            throw $customerDatePassedCountsAsNone
                ? SaleException::paymentDueDateMissing($sale->number, $sale->required_payment_percent)
                : SaleException::paymentDueDatePassed($sale->number, $dueDate);
        }
    }

    private function customer(Sale $sale, string $organizationId): Customer
    {
        /** @var Customer */
        return Customer::query()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->whereKey($sale->customer_id)
            ->firstOrFail(['id', 'name', 'is_active', 'is_walk_in', 'payment_due_date', 'deleted_at']);
    }

    /**
     * An inactive or deleted customer takes no new commitment; what is confirmed goes on.
     *
     * @throws SaleException
     */
    private function buyingCustomer(Sale $sale, string $organizationId): Customer
    {
        $customer = $this->customer($sale, $organizationId);

        if ($customer->trashed()) {
            throw SaleException::customerUnavailable($customer->id);
        }

        $this->saleAssertion->assertCustomerCanBuy($customer);

        return $customer;
    }

    private function context(Sale $sale, bool $actorMayApprove): SaleTransitionContext
    {
        return new SaleTransitionContext(
            needsApproval: $sale->approval_reasons !== null && $sale->approval_reasons !== [],
            actorMayApprove: $actorMayApprove,
        );
    }

    /**
     * Refuses a gesture the status never allows, using the most lenient guard context.
     *
     * @throws SaleException
     */
    private function assertGestureAllowed(Sale $sale, SaleEvent $event): void
    {
        $this->saleStateMachine
            ->actor($sale->status, new SaleTransitionContext(needsApproval: false, actorMayApprove: true))
            ->trigger($event, $this->refusal($sale));
    }

    /**
     * Saves the sale at its new status and records the transition.
     *
     * @throws SaleException If the status does not allow the event.
     */
    private function move(Sale $sale, SaleEvent $event, SaleTransitionContext $context, ?string $reason = null): void
    {
        $result = $this->saleStateMachine
            ->actor($sale->status, $context)
            ->trigger($event, $this->refusal($sale));

        $sale->status = SaleStatus::from(StateMachineValue::normalize($result->to));
        $sale->save();
        SaleTransition::query()->create([
            'organization_id' => $sale->organization_id,
            'sale_id'         => $sale->id,
            'event'           => $result->event,
            'from_status'     => $result->from,
            'to_status'       => $result->to,
            'member_id'       => $this->currentMemberId(),
            'reason'          => $reason,
        ]);
    }

    /** @return Closure(TransitionFailure): SaleException */
    private function refusal(Sale $sale): Closure
    {
        return fn (TransitionFailure $failure): SaleException => SaleException::gestureNotAllowed(
            $sale->number,
            StateMachineValue::normalize($failure->state),
            StateMachineValue::normalize($failure->event),
            array_map(fn (UnitEnum $event): string => StateMachineValue::normalize($event), $failure->allowedEvents),
        );
    }

    private function currentMemberId(): ?string
    {
        $memberId = authContext()->member()?->getKey();

        return $memberId === null ? null : (string) $memberId;
    }
}
