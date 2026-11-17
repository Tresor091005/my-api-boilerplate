<?php

declare(strict_types=1);

namespace Lahatre\Sale\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Contracts\CatalogInterface;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Customer\Models\Customer;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Sale\Assertions\SaleAssertion;
use Lahatre\Sale\Data\FulfillmentLineData;
use Lahatre\Sale\Data\PlannedFulfillmentLine;
use Lahatre\Sale\Data\SaleTransitionContext;
use Lahatre\Sale\Enums\FulfillmentPurpose;
use Lahatre\Sale\Enums\FulfillmentStatus;
use Lahatre\Sale\Enums\SaleApprovalReason;
use Lahatre\Sale\Enums\SaleEvent;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Sale\Models\Fulfillment;
use Lahatre\Sale\Models\FulfillmentLine;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleHold;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Sale\Models\SaleTransition;
use Lahatre\Sale\Validation\FulfillmentSourcesValidator;
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
        private FulfillmentSourcesValidator $fulfillmentSourcesValidator,
        private FulfillmentWriter $fulfillmentWriter,
        private FulfillmentStockService $fulfillmentStockService,
        private CatalogInterface $catalogInterface,
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
     * A counter sale delivers everything from the sources given, a bill-and-hold sale sets everything aside
     * from them, and a progress-billing sale ignores them. Creates no invoice.
     *
     * @param  bool  $keepApprovedPrices  whether to sell at the approved prices when the catalog prices moved since
     * @param  Collection<int, FulfillmentLineData>  $lines  where each line leaves from
     *
     * @throws SaleException If the sale is not approved, a credit's due date has passed, something is no longer sellable, a catalog price moved and the approved prices are not kept, its amounts changed, or the stock is lacking.
     * @throws TaxException If the taxes of a line cannot be calculated.
     * @throws ValidationException Naming each source that is refused.
     */
    public function confirm(Sale $sale, bool $keepApprovedPrices = false, ?Collection $lines = null): Sale
    {
        return $this->withLockedSale($sale, function (Sale $sale, string $organizationId) use ($keepApprovedPrices, $lines): void {
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
            $this->fulfillAtConfirmation($sale, $lines ?? collect());

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
            $this->settleCancellation($sale);
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
     * Makes a sale being cancelled `cancelled` once nothing it delivered is still out: what it set
     * aside goes back to the warehouses and its draft fulfillments are dropped. Assumes the caller's
     * transaction and the sale's lock.
     */
    public function settleCancellation(Sale $sale): void
    {
        if ($sale->status !== SaleStatus::Cancelling) {
            return;
        }

        $stillDelivered = FulfillmentLine::query()
            ->where('organization_id', $sale->organization_id)
            ->whereHas('fulfillment', fn (Builder $fulfillment): Builder => $fulfillment->where('sale_id', $sale->id)->where('status', FulfillmentStatus::Completed))
            ->exists();
        if ($stillDelivered) {
            return;
        }

        $this->releaseHolds($sale);
        Fulfillment::query()
            ->where('organization_id', $sale->organization_id)
            ->where('sale_id', $sale->id)
            ->where('status', FulfillmentStatus::Draft)
            ->delete();

        $sale->cancelled_at = now();
        $this->move($sale, SaleEvent::CompleteCancellation, $this->context($sale, false));
    }

    /**
     * @param  Collection<int, FulfillmentLineData>  $lines
     *
     * @throws SaleException
     * @throws ValidationException
     */
    private function fulfillAtConfirmation(Sale $sale, Collection $lines): void
    {
        if ($sale->mode === SaleMode::Counter) {
            $planned = $this->fulfillmentSourcesValidator->plan($sale, $lines, FulfillmentPurpose::CounterConfirmation);
            $this->fulfillmentWriter->complete($this->fulfillmentWriter->createDraft($sale, []), $planned);

            return;
        }

        if ($sale->mode === SaleMode::BillAndHold) {
            $this->setAside($sale, $this->fulfillmentSourcesValidator->plan($sale, $lines, FulfillmentPurpose::HoldConfirmation));
        }
    }

    /**
     * @param  list<PlannedFulfillmentLine>  $planned
     *
     * @throws SaleException
     */
    private function setAside(Sale $sale, array $planned): void
    {
        $holdLocations = collect($planned)
            ->map(fn (PlannedFulfillmentLine $line): ?StockLocation => $line->warehouse)
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn (StockLocation $warehouse): array => [
                $warehouse->id => $this->catalogInterface->customerHoldLocation($sale->organization_id, $warehouse->id)->load('inventoryLocation'),
            ]);
        $transactionId = $this->fulfillmentStockService->setAside($sale->id, $planned, $holdLocations);
        if ($transactionId === null) {
            return;
        }

        $now = now();
        SaleHold::query()->insert(array_map(fn (PlannedFulfillmentLine $line): array => [
            'id'                       => (string) Str::uuid7(),
            'organization_id'          => $sale->organization_id,
            'sale_id'                  => $sale->id,
            'sale_line_id'             => $line->saleLine->id,
            'stock_location_id'        => (string) $line->warehouse?->id,
            'quantity'                 => $line->quantity,
            'released_quantity'        => 0,
            'inventory_transaction_id' => $transactionId,
            'created_at'               => $now,
            'updated_at'               => $now,
        ], array_values(array_filter($planned, fn (PlannedFulfillmentLine $line): bool => $line->isTracked && $line->warehouse instanceof StockLocation))));
    }

    /** Moves back to each warehouse what is still set aside there; called once nothing is delivered. */
    private function releaseHolds(Sale $sale): void
    {
        $holds = SaleHold::query()
            ->where('organization_id', $sale->organization_id)
            ->where('sale_id', $sale->id)
            ->whereColumn('released_quantity', '<', 'quantity')
            ->with(['saleLine:id,catalog_item_id'])
            ->get();
        if ($holds->isEmpty()) {
            return;
        }

        $items = CatalogItem::query()
            ->where('organization_id', $sale->organization_id)
            ->whereIn('id', $holds->pluck('saleLine.catalog_item_id')->unique()->values())
            ->with('inventoryItem')
            ->get()
            ->keyBy('id');
        $warehouses = StockLocation::withTrashed()
            ->where('organization_id', $sale->organization_id)
            ->whereIn('id', $holds->pluck('stock_location_id')->unique()->values())
            ->with('inventoryLocation')
            ->get()
            ->keyBy('id');
        $holdLocations = StockLocation::query()
            ->where('organization_id', $sale->organization_id)
            ->whereIn('held_for_location_id', $warehouses->keys())
            ->with('inventoryLocation')
            ->get()
            ->keyBy('held_for_location_id');

        $transactionId = $this->fulfillmentStockService->release($sale->id, $holds->map(fn (SaleHold $hold): array => [
            'item'     => $items->get($hold->saleLine->catalog_item_id)?->inventoryItem,
            'from'     => $holdLocations->get($hold->stock_location_id)?->inventoryLocation,
            'to'       => $warehouses->get($hold->stock_location_id)?->inventoryLocation,
            'quantity' => $hold->quantity - $hold->released_quantity,
        ])->values()->all());

        SaleHold::query()
            ->where('organization_id', $sale->organization_id)
            ->whereIn('id', $holds->modelKeys())
            ->update(['released_quantity' => DB::raw('quantity'), 'release_transaction_id' => $transactionId, 'updated_at' => now()]);
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
            // only the system completes a cancellation, so it is never offered
            array_values(array_map(
                fn (UnitEnum $event): string => StateMachineValue::normalize($event),
                array_filter($failure->allowedEvents, fn (UnitEnum $event): bool => $event !== SaleEvent::CompleteCancellation),
            )),
        );
    }

    private function currentMemberId(): ?string
    {
        $memberId = authContext()->member()?->getKey();

        return $memberId === null ? null : (string) $memberId;
    }
}
