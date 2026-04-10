<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Inventory\DTO\InventoryItemFilterDTO;
use Lahatre\Inventory\DTO\InventoryLocationFilterDTO;
use Lahatre\Inventory\DTO\InventoryLotFilterDTO;
use Lahatre\Inventory\DTO\InventoryMovementFilterDTO;
use Lahatre\Inventory\DTO\InventoryStockExpiringFilterDTO;
use Lahatre\Inventory\DTO\InventoryStockSummaryFilterDTO;
use Lahatre\Inventory\DTO\InventoryTransactionFilterDTO;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Http\Resources\InventoryExpiringLotCollection;
use Lahatre\Inventory\Http\Resources\InventoryItemCollection;
use Lahatre\Inventory\Http\Resources\InventoryItemResource;
use Lahatre\Inventory\Http\Resources\InventoryLocationCollection;
use Lahatre\Inventory\Http\Resources\InventoryLocationResource;
use Lahatre\Inventory\Http\Resources\InventoryMovementCollection;
use Lahatre\Inventory\Http\Resources\InventorySummaryCollection;
use Lahatre\Inventory\Http\Resources\InventoryTransactionCollection;
use Lahatre\Inventory\Http\Resources\InventoryTransactionResource;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\ViewData\AvailableLotViewData;
use Lahatre\Inventory\ViewData\ItemLocationLotsViewData;
use Lahatre\Inventory\ViewData\ItemStockLocationViewData;
use Lahatre\Inventory\ViewData\ItemStockViewData;
use Lahatre\Inventory\ViewData\LocationStockItemViewData;
use Lahatre\Inventory\ViewData\LocationStockViewData;

class InventoryQueryService
{
    public function listItems(InventoryItemFilterDTO $filters, bool $includeItemable = false): InventoryItemCollection
    {
        $query = InventoryItem::query();

        if ($filters->ids) {
            $query->whereIn('id', $filters->ids);
        }

        if ($filters->itemable_type && $filters->itemable_id) {
            $query->where('itemable_type', $filters->itemable_type)
                ->whereIn('itemable_id', $filters->itemable_id);
        }

        if ($filters->sku) {
            $query->where('sku', 'like', "$filters->sku%");
        }

        if ($filters->base_unit_code) {
            $query->where('base_unit_code', $filters->base_unit_code);
        }

        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }

        if ($includeItemable) {
            $query->with('itemable');
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);
        if ($filters->sort_by !== 'id') {
            $query->orderBy('id');
        }

        $items = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return InventoryItemCollection::make($items);
    }

    public function retrieveItem(InventoryItem $item, bool $includeItemable = false): InventoryItemResource
    {
        if ($includeItemable) {
            $item->load('itemable');
        }

        return InventoryItemResource::make($item);
    }

    public function listLocations(InventoryLocationFilterDTO $filters, bool $includeExternal = false): InventoryLocationCollection
    {
        $query = InventoryLocation::query();

        if ($filters->ids) {
            $query->whereIn('id', $filters->ids);
        }

        if ($filters->external_type && $filters->external_id) {
            $query->where('external_type', $filters->external_type)
                ->whereIn('external_id', $filters->external_id);
        }

        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }

        if ($includeExternal) {
            $query->with('external');
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);
        if ($filters->sort_by !== 'id') {
            $query->orderBy('id');
        }

        $locations = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return InventoryLocationCollection::make($locations);
    }

    public function retrieveLocation(InventoryLocation $location, bool $includeExternal = false): InventoryLocationResource
    {
        if ($includeExternal) {
            $location->load('external');
        }

        return InventoryLocationResource::make($location);
    }

    public function getItemStock(InventoryItem $item): ItemStockViewData
    {
        $locations = InventoryStock::query()
            ->select('location_id')
            ->selectRaw('SUM(remaining) as remaining')
            ->where('item_id', $item->id)
            ->where('remaining', '>', 0)
            ->groupBy('location_id')
            ->orderBy('location_id')
            ->get();

        return new ItemStockViewData(
            itemId: $item->id,
            totalRemaining: (int) $locations->sum('remaining'),
            unitCode: $item->base_unit_code,
            locations: $locations
                ->map(fn (InventoryStock $stock): ItemStockLocationViewData => new ItemStockLocationViewData(
                    locationId: $stock->location_id,
                    remaining: (int) $stock->remaining,
                ))
                ->values()
        );
    }

    public function getLocationStock(InventoryLocation $location): LocationStockViewData
    {
        $aggregatedStocks = InventoryStock::query()
            ->select('item_id')
            ->selectRaw('SUM(remaining) as remaining')
            ->where('location_id', $location->id)
            ->where('remaining', '>', 0)
            ->groupBy('item_id')
            ->get();

        /** @var Collection<string, InventoryItem> $items */
        $items = InventoryItem::query()
            ->whereIn('id', $aggregatedStocks->pluck('item_id')->all())
            ->get()
            ->keyBy('id');

        return new LocationStockViewData(
            locationId: $location->id,
            items: $aggregatedStocks
                ->map(function (InventoryStock $stock) use ($items): LocationStockItemViewData {
                    $item = $items->get($stock->item_id);

                    return new LocationStockItemViewData(
                        itemId: $stock->item_id,
                        sku: $item?->sku,
                        remaining: (int) $stock->remaining,
                        unitCode: $item?->base_unit_code,
                    );
                })
                ->sortBy(fn (LocationStockItemViewData $stockItem): array => [$stockItem->sku ?? '', $stockItem->itemId])
                ->values()
        );
    }

    public function getItemLocationLots(
        InventoryItem $item,
        InventoryLocation $location,
        InventoryLotFilterDTO $filters
    ): ItemLocationLotsViewData {
        $strategy = $filters->strategy
            ?? $item->deduction_strategy
            ?? DeductionStrategy::tryFrom((string) config('inventory.default_strategy'))
            ?? DeductionStrategy::Fifo;

        $query = InventoryStock::query()
            ->where('item_id', $item->id)
            ->where('location_id', $location->id)
            ->where('remaining', '>', 0);

        if ($filters->expiring_before instanceof CarbonImmutable) {
            $query->where('expiration_date', '<=', $filters->expiring_before->endOfDay());
        }

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
                currencyCode: $stock->currency_code,
                expirationDate: $stock->expiration_date,
                createdAt: $stock->created_at,
                metadata: $stock->metadata,
            ))->values()
        );
    }

    public function listSummary(InventoryStockSummaryFilterDTO $filters): InventorySummaryCollection
    {
        $query = DB::table('inventory_stocks as stocks')
            ->join('inventory_items as items', 'items.id', '=', 'stocks.item_id')
            ->join('inventory_locations as locations', 'locations.id', '=', 'stocks.location_id')
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
            ])
            ->groupBy('stocks.item_id', 'stocks.location_id', 'items.sku', 'items.base_unit_code')
            ->orderBy('stocks.item_id')
            ->orderBy('stocks.location_id');

        $itemIds = collect($filters->item_id)->filter()->values();
        $locationIds = collect($filters->location_id)->filter()->values();

        if ($itemIds->isNotEmpty()) {
            $query->whereIn('stocks.item_id', $itemIds->all());
        }

        if ($locationIds->isNotEmpty()) {
            $query->whereIn('stocks.location_id', $locationIds->all());
        }

        $paginator = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return InventorySummaryCollection::make($paginator);
    }

    public function listExpiring(InventoryStockExpiringFilterDTO $filters): InventoryExpiringLotCollection
    {
        $query = InventoryStock::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_stocks.item_id')
            ->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stocks.location_id')
            ->whereNull('inventory_items.deleted_at')
            ->whereNull('inventory_locations.deleted_at')
            ->where('inventory_stocks.remaining', '>', 0)
            ->whereNotNull('inventory_stocks.expiration_date')
            ->where('inventory_stocks.expiration_date', '<=', now()->addDays($filters->days)->endOfDay())
            ->select('inventory_stocks.*')
            ->orderBy('inventory_stocks.expiration_date')
            ->orderBy('inventory_stocks.created_at');

        if ($filters->location_id) {
            $query->where('inventory_stocks.location_id', $filters->location_id);
        }

        $paginator = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['inventory_stocks.*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page, ['inventory_stocks.*']);

        return InventoryExpiringLotCollection::make($paginator);
    }

    public function listItemMovements(InventoryItem $item, InventoryMovementFilterDTO $filters): InventoryMovementCollection
    {
        $query = InventoryMovement::query()
            ->with(['stock', 'location', 'unit', 'currency'])
            ->where('item_id', $item->id);

        $this->applyMovementFilters($query, $filters);

        $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return InventoryMovementCollection::make($paginator);
    }

    public function listLocationMovements(InventoryLocation $location, InventoryMovementFilterDTO $filters): InventoryMovementCollection
    {
        $query = InventoryMovement::query()
            ->with(['stock', 'location', 'unit', 'currency'])
            ->where('location_id', $location->id);

        $this->applyMovementFilters($query, $filters);

        $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return InventoryMovementCollection::make($paginator);
    }

    public function retrieveTransaction(InventoryTransaction $transaction): InventoryTransactionResource
    {
        $transaction->load([
            'movements.stock.unit',
            'movements.stock.currency',
            'movements.location',
            'movements.unit',
            'movements.currency',
        ]);

        return InventoryTransactionResource::make($transaction);
    }

    public function listTransactions(InventoryTransactionFilterDTO $filters): InventoryTransactionCollection
    {
        $query = InventoryTransaction::query();

        if ($filters->ids) {
            $query->whereIn('id', $filters->ids);
        }

        if ($filters->reference_type && $filters->reference_id) {
            $query->where('reference_type', $filters->reference_type)
                ->whereIn('reference_id', $filters->reference_id);
        }

        if ($filters->transaction_type) {
            $query->where('transaction_type', $filters->transaction_type);
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);
        if ($filters->sort_by !== 'id') {
            $query->orderBy('id');
        }

        $transactions = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return InventoryTransactionCollection::make($transactions);
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

    protected function applyMovementFilters(Builder $query, InventoryMovementFilterDTO $filters): void
    {
        if ($filters->from instanceof CarbonImmutable) {
            $query->where('created_at', '>=', $filters->from->startOfDay());
        }

        if ($filters->to instanceof CarbonImmutable) {
            $query->where('created_at', '<=', $filters->to->endOfDay());
        }

        if ($filters->movement_type) {
            $query->where('movement_type', $filters->movement_type);
        }

        if ($filters->reference_type || $filters->reference_id) {
            $query->whereHas('transaction', function (Builder $transactionQuery) use ($filters): void {
                if ($filters->reference_type) {
                    $transactionQuery->where('reference_type', $filters->reference_type);
                }

                if ($filters->reference_id) {
                    $transactionQuery->where('reference_id', $filters->reference_id);
                }
            });
        }
    }
}
