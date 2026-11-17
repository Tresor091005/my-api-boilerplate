<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Lahatre\Inventory\Data\InventoryMovementFilterData;
use Lahatre\Inventory\Data\InventoryStockFilterData;
use Lahatre\Inventory\Data\InventoryStockSummaryFilterData;
use Lahatre\Inventory\Data\InventoryTransactionFilterData;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Exceptions\OrganizationScopeException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Master\Contracts\MasterInterface;

class InventoryQueryService
{
    public function __construct(
        protected MasterInterface $masterInterface
    ) {}

    public function paginateSummary(InventoryStockSummaryFilterData $filters): CursorPaginator
    {
        return $this->summaryQuery($filters)->cursorPaginate($filters->perPage, ['*'], 'cursor', $filters->cursor);
    }

    public function paginateStocks(InventoryStockFilterData $filters): CursorPaginator
    {
        $query = InventoryStock::query()
            ->where('organization_id', currentOrganizationId());

        if ($filters->itemId) {
            $query->whereIn('item_id', $filters->itemId);
        }

        if ($filters->locationId) {
            $query->whereIn('location_id', $filters->locationId);
        }

        if ($filters->hasRemaining === true || $filters->sortBy === 'deduction') {
            $query->where('remaining', '>', 0);
        } elseif ($filters->hasRemaining === false) {
            $query->where('remaining', 0);
        }

        if ($filters->expiringBefore instanceof CarbonImmutable) {
            $query->where('remaining', '>', 0)
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<=', $filters->expiringBefore->toDateString())
                ->whereHas('item', fn (Builder $itemQuery): Builder => $itemQuery->where('is_expirable', true))
                ->whereHas('location');
        }

        if ($filters->sortBy === 'deduction') {
            $this->orderAsDeducted($query, $filters);
        } elseif ($filters->expiringBefore instanceof CarbonImmutable) {
            $query->orderBy('expiration_date')->orderBy('created_at')->orderBy('id');
        } else {
            $query->orderBy('id');
        }

        return $query->cursorPaginate($filters->perPage, ['*'], 'cursor', $filters->cursor);
    }

    /**
     * The order an outgoing movement takes the lots of one item in: the strategy asked, else the
     * item's, else FEFO for an item that expires and FIFO otherwise. The lots of an item that
     * expires all carry a date, and FEFO on an item that does not is FIFO, so the order needs no
     * empty dates and stays cursor-paginable.
     *
     * @param  Builder<InventoryStock>  $query
     */
    private function orderAsDeducted(Builder $query, InventoryStockFilterData $filters): void
    {
        $item = InventoryItem::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($filters->itemId[0] ?? null)
            ->first(['id', 'deduction_strategy', 'is_expirable']);
        $strategy = $filters->strategy
            ?? $item->deduction_strategy
            ?? ($item?->is_expirable === true ? DeductionStrategy::Fefo : DeductionStrategy::Fifo);

        if ($strategy === DeductionStrategy::Fefo && $item?->is_expirable === true) {
            $query->orderBy('expiration_date');
        }

        $query->orderBy('created_at')->orderBy('id');
    }

    private function summaryQuery(InventoryStockSummaryFilterData $filters): QueryBuilder
    {
        $query = DB::table('inventory_stocks as stocks')
            ->join('inventory_items as items', 'items.id', '=', 'stocks.item_id')
            ->join('inventory_locations as locations', 'locations.id', '=', 'stocks.location_id')
            ->where('stocks.organization_id', currentOrganizationId())
            ->where('items.organization_id', currentOrganizationId())
            ->where('locations.organization_id', currentOrganizationId())
            ->whereNull('stocks.deleted_at')
            ->whereNull('items.deleted_at')
            ->whereNull('locations.deleted_at')
            ->where('stocks.remaining', '>', 0)
            ->select([
                'stocks.item_id',
                'stocks.location_id',
                'items.sku',
                'items.base_unit_code',
                DB::raw('SUM(stocks.remaining) as remaining'),
                'stocks.currency_code',
                DB::raw('SUM((stocks.remaining::numeric * stocks.unit_cost::numeric) + stocks.cost_remainder::numeric) as total_value_minor'),
            ])
            ->groupBy('stocks.item_id', 'stocks.location_id', 'items.sku', 'items.base_unit_code', 'stocks.currency_code')
            ->orderBy('stocks.item_id')
            ->orderBy('stocks.location_id');

        $itemIds = collect($filters->itemId)->filter()->values();
        $locationIds = collect($filters->locationId)->filter()->values();

        if ($itemIds->isNotEmpty()) {
            $query->whereIn('stocks.item_id', $itemIds->all());
        }

        if ($locationIds->isNotEmpty()) {
            $query->whereIn('stocks.location_id', $locationIds->all());
        }

        return $query;
    }

    public function paginateMovements(InventoryMovementFilterData $filters): CursorPaginator
    {
        return $this->movementsQuery($filters)
            ->cursorPaginate($filters->perPage, ['*'], 'cursor', $filters->cursor);
    }

    /** @return Builder<InventoryMovement> */
    private function movementsQuery(InventoryMovementFilterData $filters): Builder
    {
        $query = InventoryMovement::query()
            ->where('organization_id', currentOrganizationId());

        $this->applyMovementFilters($query, $filters);

        $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $query;
    }

    public function retrieveTransaction(InventoryTransaction $transaction): InventoryTransaction
    {
        return $this->resolveTransaction($transaction);
    }

    public function paginateTransactions(InventoryTransactionFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate($this->transactionsQuery($filters), $filters);
    }

    /** @return Builder<InventoryTransaction> */
    private function transactionsQuery(InventoryTransactionFilterData $filters): Builder
    {
        $query = InventoryTransaction::query()->where('organization_id', currentOrganizationId());

        if ($filters->ids) {
            $query->whereIn('id', $filters->ids);
        }

        if ($filters->referenceType && $filters->referenceId) {
            $query->where('reference_type', $filters->referenceType)
                ->whereIn('reference_id', $filters->referenceId);
        }

        if ($filters->transactionType) {
            $query->where('transaction_type', $filters->transactionType);
        }

        return $query;
    }

    protected function applyMovementFilters(Builder $query, InventoryMovementFilterData $filters): void
    {
        if ($filters->itemId) {
            $query->whereIn('item_id', $filters->itemId);
        }

        if ($filters->locationId) {
            $query->whereIn('location_id', $filters->locationId);
        }

        if ($filters->from instanceof CarbonImmutable) {
            $query->where('created_at', '>=', $filters->from->startOfDay());
        }

        if ($filters->to instanceof CarbonImmutable) {
            $query->where('created_at', '<=', $filters->to->endOfDay());
        }

        if ($filters->movementType) {
            $query->where('movement_type', $filters->movementType);
        }

        if ($filters->transactionId) {
            $query->where('transaction_id', $filters->transactionId);
        }

        if ($filters->stockId) {
            $query->where('stock_id', $filters->stockId);
        }

        if ($filters->referenceType || $filters->referenceId) {
            $query->whereHas('transaction', function (Builder $transactionQuery) use ($filters): void {
                if ($filters->referenceType) {
                    $transactionQuery->where('reference_type', $filters->referenceType);
                }

                if ($filters->referenceId) {
                    $transactionQuery->where('reference_id', $filters->referenceId);
                }
            });
        }
    }

    protected function resolveTransaction(InventoryTransaction $transaction): InventoryTransaction
    {
        $organizationId = currentOrganizationId();
        $ownedTransaction = InventoryTransaction::query()
            ->where('organization_id', $organizationId)
            ->whereKey($transaction->getKey())
            ->first();

        if ($ownedTransaction === null) {
            throw OrganizationScopeException::mismatch();
        }

        return $ownedTransaction;
    }
}
