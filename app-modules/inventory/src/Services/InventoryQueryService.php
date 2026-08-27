<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Lahatre\Inventory\Data\InventoryItemFilterData;
use Lahatre\Inventory\Data\InventoryLocationFilterData;
use Lahatre\Inventory\Data\InventoryLotFilterData;
use Lahatre\Inventory\Data\InventoryMovementFilterData;
use Lahatre\Inventory\Data\InventoryStockExpiringFilterData;
use Lahatre\Inventory\Data\InventoryStockSummaryFilterData;
use Lahatre\Inventory\Data\InventoryTransactionFilterData;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Exceptions\OrganizationScopeException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\ViewData\AvailableLotViewData;
use Lahatre\Inventory\ViewData\ItemLocationLotsViewData;
use Lahatre\Master\Contracts\MasterInterface;

class InventoryQueryService
{
    public function __construct(
        protected MasterInterface $masterInterface
    ) {}

    public function paginateItems(InventoryItemFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate($this->itemsQuery($filters), $filters);
    }

    /** @return Builder<InventoryItem> */
    private function itemsQuery(InventoryItemFilterData $filters): Builder
    {
        $query = InventoryItem::query()->where('organization_id', currentOrganizationId());

        if ($filters->sku) {
            $query->where('sku', 'like', "$filters->sku%");
        }

        if ($filters->baseUnitCode) {
            $query->where('base_unit_code', $filters->baseUnitCode);
        }

        if ($filters->stockTrackingEnabled !== null) {
            $query->where('stock_tracking_enabled', $filters->stockTrackingEnabled);
        }

        applyResponseContextToQuery($query);

        return $query;
    }

    public function retrieveItem(InventoryItem $item): InventoryItem
    {
        $item = $this->resolveItem($item);

        return $item->load(responseRelationsToLoad());
    }

    public function paginateLocations(InventoryLocationFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate($this->locationsQuery($filters), $filters);
    }

    /** @return Builder<InventoryLocation> */
    private function locationsQuery(InventoryLocationFilterData $filters): Builder
    {
        $query = InventoryLocation::query()->where('organization_id', currentOrganizationId());

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        applyResponseContextToQuery($query);

        return $query;
    }

    public function retrieveLocation(InventoryLocation $location): InventoryLocation
    {
        $location = $this->resolveLocation($location);

        return $location->load(responseRelationsToLoad());
    }

    public function getItemLocationLots(
        InventoryItem $item,
        InventoryLocation $location,
        InventoryLotFilterData $filters
    ): ItemLocationLotsViewData {
        $item = $this->resolveItem($item);
        $location = $this->resolveLocation($location);

        $strategy = $filters->strategy
            ?? $item->deduction_strategy
            ?? ($item->is_expirable ? DeductionStrategy::Fefo : DeductionStrategy::Fifo);

        $query = InventoryStock::query()
            ->where('item_id', $item->id)
            ->where('location_id', $location->id)
            ->where('organization_id', currentOrganizationId())
            ->where('remaining', '>', 0);

        if ($filters->expiringBefore instanceof CarbonImmutable) {
            $query->where('expiration_date', '<=', $filters->expiringBefore->endOfDay());
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, InventoryStock> $lots */
        $lots = $this->applyLotOrdering($query, $strategy)->get();

        return new ItemLocationLotsViewData(
            itemId: $item->id,
            locationId: $location->id,
            deductionStrategy: $strategy->value,
            totalRemaining: (int) $lots->sum('remaining'),
            unitCode: $item->base_unit_code,
            lots: $lots->map(fn (InventoryStock $stock): AvailableLotViewData => new AvailableLotViewData(
                stockId: $stock->id,
                remaining: $stock->remaining,
                quantity: $stock->quantity,
                unitCost: $stock->unit_cost,
                costRemainder: $stock->cost_remainder,
                currencyCode: $stock->currency_code,
                expirationDate: $stock->expiration_date,
                createdAt: $stock->created_at,
                metadata: $stock->metadata,
                exchangeMetadata: $stock->exchange_metadata,
            ))->values()
        );
    }

    public function paginateSummary(InventoryStockSummaryFilterData $filters): CursorPaginator
    {
        return $this->summaryQuery($filters)->cursorPaginate($filters->perPage, ['*'], 'cursor', $filters->cursor);
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
                DB::raw('items.base_unit_code as unit_code'),
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

    public function paginateExpiring(InventoryStockExpiringFilterData $filters): CursorPaginator
    {
        return $this->expiringQuery($filters)->cursorPaginate($filters->perPage, ['inventory_stocks.*'], 'cursor', $filters->cursor);
    }

    /** @return Builder<InventoryStock> */
    private function expiringQuery(InventoryStockExpiringFilterData $filters): Builder
    {
        $query = InventoryStock::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_stocks.item_id')
            ->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stocks.location_id')
            ->where('inventory_stocks.organization_id', currentOrganizationId())
            ->where('inventory_items.organization_id', currentOrganizationId())
            ->where('inventory_locations.organization_id', currentOrganizationId())
            ->whereNull('inventory_items.deleted_at')
            ->whereNull('inventory_locations.deleted_at')
            ->where('inventory_items.is_expirable', true)
            ->where('inventory_stocks.remaining', '>', 0)
            ->whereNotNull('inventory_stocks.expiration_date')
            ->where('inventory_stocks.expiration_date', '<=', now()->addDays($filters->days)->endOfDay())
            ->select('inventory_stocks.*')
            ->orderBy('inventory_stocks.expiration_date')
            ->orderBy('inventory_stocks.created_at');

        if ($filters->locationId) {
            $query->where('inventory_stocks.location_id', $filters->locationId);
        }

        if ($filters->itemId) {
            $query->where('inventory_stocks.item_id', $filters->itemId);
        }

        $query->orderBy('inventory_stocks.id');

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
        applyResponseContextToQuery($query);

        $this->applyMovementFilters($query, $filters);

        $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $query;
    }

    public function retrieveTransaction(InventoryTransaction $transaction): InventoryTransaction
    {
        $transaction = $this->resolveTransaction($transaction);

        $transaction->load(responseRelationsToLoad());

        return $transaction;
    }

    public function paginateTransactions(InventoryTransactionFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate($this->transactionsQuery($filters), $filters);
    }

    /** @return Builder<InventoryTransaction> */
    private function transactionsQuery(InventoryTransactionFilterData $filters): Builder
    {
        $query = InventoryTransaction::query()->where('organization_id', currentOrganizationId());
        applyResponseContextToQuery($query);

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

    protected function applyLotOrdering(Builder $query, DeductionStrategy $strategy): Builder
    {
        return match ($strategy) {
            DeductionStrategy::Fifo => $query->orderBy('created_at')->orderBy('id'),
            DeductionStrategy::Fefo => $query->orderByRaw('expiration_date ASC NULLS LAST')
                ->orderBy('created_at')
                ->orderBy('id'),
            DeductionStrategy::Manual => $query->orderBy('created_at')->orderBy('id'),
        };
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

    protected function resolveItem(InventoryItem $item): InventoryItem
    {
        $organizationId = currentOrganizationId();
        $ownedItem = InventoryItem::query()
            ->where('organization_id', $organizationId)
            ->whereKey($item->getKey())
            ->first();

        if ($ownedItem === null) {
            throw OrganizationScopeException::mismatch();
        }

        return $ownedItem;
    }

    protected function resolveLocation(InventoryLocation $location): InventoryLocation
    {
        $organizationId = currentOrganizationId();
        $ownedLocation = InventoryLocation::query()
            ->where('organization_id', $organizationId)
            ->whereKey($location->getKey())
            ->first();

        if ($ownedLocation === null) {
            throw OrganizationScopeException::mismatch();
        }

        return $ownedLocation;
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
