<?php

declare(strict_types=1);

namespace Lahatre\Sale\Services;

use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Sale\Data\FulfillmentFilterData;
use Lahatre\Sale\Data\FulfillmentLineData;
use Lahatre\Sale\Enums\FulfillmentPurpose;
use Lahatre\Sale\Enums\FulfillmentStatus;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Sale\Models\Fulfillment;
use Lahatre\Sale\Models\FulfillmentLine;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Validation\FulfillmentSourcesValidator;

/**
 * The fulfillments of a sale after its confirmation. Every gesture locks the sale, then the fulfillment.
 */
final readonly class FulfillmentService
{
    public function __construct(
        private FulfillmentSourcesValidator $sourcesValidator,
        private FulfillmentStockService $fulfillmentStockService,
        private FulfillmentWriter $fulfillmentWriter,
        private SaleWorkflowService $saleWorkflowService,
        private MasterInterface $masterInterface,
    ) {}

    /** @return CursorPaginator<int, Fulfillment> */
    public function paginate(Sale $sale, FulfillmentFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            Fulfillment::query()
                ->where('organization_id', currentOrganizationId())
                ->where('sale_id', $sale->id)
                ->when($filters->status, fn ($query, FulfillmentStatus $status) => $query->where('status', $status)),
            $filters,
        );
    }

    /**
     * Prepares a draft: it touches no stock.
     *
     * @param  Collection<int, FulfillmentLineData>  $lines
     *
     * @throws SaleException If the sale is not active, or the stock asked is lacking.
     * @throws ValidationException Naming each field of the request that is refused.
     */
    public function create(Sale $sale, Collection $lines): Fulfillment
    {
        return $this->withLockedSale($sale, function (Sale $sale) use ($lines): Fulfillment {
            return $this->fulfillmentWriter->createDraft($sale, $this->sourcesValidator->plan($sale, $lines, $this->purpose($sale)));
        });
    }

    /**
     * Replaces the lines of a draft whole.
     *
     * @param  Collection<int, FulfillmentLineData>  $lines
     *
     * @throws SaleException If the sale is not active, the fulfillment is not a draft, or the stock asked is lacking.
     * @throws ValidationException
     */
    public function replace(Sale $sale, Fulfillment $fulfillment, Collection $lines): Fulfillment
    {
        return $this->withLockedFulfillment($sale, $fulfillment, function (Sale $sale, Fulfillment $fulfillment) use ($lines): Fulfillment {
            $this->assertDraft($fulfillment);
            $this->fulfillmentWriter->replaceLines($fulfillment, $this->sourcesValidator->plan($sale, $lines, $this->purpose($sale)));
            $fulfillment->touch();

            return $fulfillment;
        });
    }

    /** @throws SaleException If the fulfillment is not a draft. */
    public function delete(Sale $sale, Fulfillment $fulfillment): void
    {
        $this->withLockedFulfillment($sale, $fulfillment, function (Sale $sale, Fulfillment $fulfillment): Fulfillment {
            $this->assertDraft($fulfillment);
            $fulfillment->delete();

            return $fulfillment;
        }, saleMustBeActive: false);
    }

    /**
     * Checks the draft again and takes its goods out of stock.
     *
     * @throws SaleException If the sale is not active, the fulfillment is not a draft, or the stock is lacking.
     * @throws ValidationException If a line no longer fits what is left to deliver or set aside.
     */
    public function complete(Sale $sale, Fulfillment $fulfillment): Fulfillment
    {
        return $this->withLockedFulfillment($sale, $fulfillment, function (Sale $sale, Fulfillment $fulfillment): Fulfillment {
            $this->assertDraft($fulfillment);

            return $this->fulfillmentWriter->complete($fulfillment, $this->sourcesValidator->plan($sale, $this->storedLines($fulfillment), $this->purpose($sale)));
        });
    }

    /**
     * Gives the goods back to the lots they left. On a sale being cancelled, it may let the sale become `cancelled`.
     *
     * @throws SaleException If the fulfillment is not completed.
     */
    public function cancel(Sale $sale, Fulfillment $fulfillment): Fulfillment
    {
        return $this->withLockedFulfillment($sale, $fulfillment, function (Sale $sale, Fulfillment $fulfillment): Fulfillment {
            if ($fulfillment->status !== FulfillmentStatus::Completed) {
                throw SaleException::fulfillmentNotCompleted($fulfillment->number, $fulfillment->status->value);
            }

            $fulfillment->fill([
                'status'                  => FulfillmentStatus::Cancelled,
                'reversal_transaction_id' => $fulfillment->inventory_transaction_id === null
                    ? null
                    : $this->fulfillmentStockService->giveBack($fulfillment->inventory_transaction_id, $fulfillment->id),
                'cancelled_at' => now(),
            ])->save();
            $this->saleWorkflowService->settleCancellation($sale);

            return $fulfillment;
        }, saleMustBeActive: false);
    }

    /** A bill-and-hold sale delivers what it set aside; the others take from the warehouses. */
    private function purpose(Sale $sale): FulfillmentPurpose
    {
        return $sale->mode === SaleMode::BillAndHold ? FulfillmentPurpose::FromHold : FulfillmentPurpose::FromWarehouse;
    }

    /** @throws SaleException */
    private function assertDraft(Fulfillment $fulfillment): void
    {
        if ($fulfillment->status !== FulfillmentStatus::Draft) {
            throw SaleException::fulfillmentNotDraft($fulfillment->number, $fulfillment->status->value);
        }
    }

    /**
     * The stored lines of a draft, back in the shape of a request, quantities in the unit sold in.
     *
     * @return Collection<int, FulfillmentLineData>
     */
    private function storedLines(Fulfillment $fulfillment): Collection
    {
        return FulfillmentLine::query()
            ->where('organization_id', $fulfillment->organization_id)
            ->where('fulfillment_id', $fulfillment->id)
            ->with('saleLine:id,display_unit_code,quantity')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('sale_line_id')
            ->values()
            ->map(fn (Collection $rows): FulfillmentLineData => FulfillmentLineData::fromArray([
                'sale_line_id' => $rows->first()->sale_line_id,
                'sources'      => $rows->map(fn (FulfillmentLine $row): array => [
                    'stock_location_id' => $row->stock_location_id,
                    'quantity'          => $this->toUnitSold($row),
                    'stock_ids'         => $row->stock_ids,
                ])->all(),
            ]));
    }

    private function toUnitSold(FulfillmentLine $row): int
    {
        $unitCode = $row->saleLine->display_unit_code;

        return (int) $this->masterInterface->convertUnitFromBase((string) $row->quantity, $unitCode)['amount'];
    }

    /**
     * @template TResult
     *
     * @param  Closure(Sale): TResult  $apply
     * @return TResult
     */
    private function withLockedSale(Sale $sale, Closure $apply, bool $saleMustBeActive = true): mixed
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($sale, $apply, $organizationId, $saleMustBeActive): mixed {
            /** @var Sale $locked */
            $locked = Sale::query()
                ->where('organization_id', $organizationId)
                ->whereKey($sale->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($saleMustBeActive && $locked->status !== SaleStatus::Active) {
                throw SaleException::saleNotFulfillable($locked->number, $locked->status->value);
            }

            return $apply($locked);
        });
    }

    /**
     * @param  Closure(Sale, Fulfillment): Fulfillment  $apply
     */
    private function withLockedFulfillment(Sale $sale, Fulfillment $fulfillment, Closure $apply, bool $saleMustBeActive = true): Fulfillment
    {
        return $this->withLockedSale($sale, function (Sale $sale) use ($fulfillment, $apply): Fulfillment {
            /** @var Fulfillment $locked */
            $locked = Fulfillment::query()
                ->where('organization_id', $sale->organization_id)
                ->where('sale_id', $sale->id)
                ->whereKey($fulfillment->id)
                ->lockForUpdate()
                ->firstOrFail();

            return $apply($sale, $locked);
        }, $saleMustBeActive);
    }
}
