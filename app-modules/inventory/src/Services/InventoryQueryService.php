<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Inventory\Data\InventoryItemFilterData;
use Lahatre\Inventory\Data\InventoryItemValueFilterData;
use Lahatre\Inventory\Data\InventoryLocationFilterData;
use Lahatre\Inventory\Data\InventoryLocationValueFilterData;
use Lahatre\Inventory\Data\InventoryLotFilterData;
use Lahatre\Inventory\Data\InventoryMovementFilterData;
use Lahatre\Inventory\Data\InventoryStockExpiringFilterData;
use Lahatre\Inventory\Data\InventoryStockSummaryFilterData;
use Lahatre\Inventory\Data\InventoryTransactionFilterData;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Exceptions\OrganizationScopeException;
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
use Lahatre\Inventory\Traits\ResolvesInventoryOrganization;
use Lahatre\Inventory\ViewData\AvailableLotViewData;
use Lahatre\Inventory\ViewData\CurrencyValueViewData;
use Lahatre\Inventory\ViewData\ItemLocationLotsViewData;
use Lahatre\Inventory\ViewData\ItemStockLocationViewData;
use Lahatre\Inventory\ViewData\ItemStockViewData;
use Lahatre\Inventory\ViewData\ItemValueLocationViewData;
use Lahatre\Inventory\ViewData\ItemValueViewData;
use Lahatre\Inventory\ViewData\LocationStockItemViewData;
use Lahatre\Inventory\ViewData\LocationStockViewData;
use Lahatre\Inventory\ViewData\LocationValueItemViewData;
use Lahatre\Inventory\ViewData\LocationValueViewData;
use Lahatre\Master\Contracts\MasterInterface;

class InventoryQueryService
{
    use ResolvesInventoryOrganization;

    public function __construct(
        protected MasterInterface $masterInterface
    ) {}

    public function listItems(InventoryItemFilterData $filters, bool $includeItemable = false): InventoryItemCollection
    {
        $query = InventoryItem::query()->where('organization_id', $this->organizationId());

        if ($filters->ids) {
            $query->whereIn('id', $filters->ids);
        }

        if ($filters->itemableType && $filters->itemableId) {
            $query->where('itemable_type', $filters->itemableType)
                ->whereIn('itemable_id', $filters->itemableId);
        }

        if ($filters->sku) {
            $query->where('sku', 'like', "$filters->sku%");
        }

        if ($filters->baseUnitCode) {
            $query->where('base_unit_code', $filters->baseUnitCode);
        }

        if ($filters->stockTrackingEnabled !== null) {
            $query->where('stock_tracking_enabled', $filters->stockTrackingEnabled);
        }

        if ($includeItemable) {
            $query->with('itemable');
        }

        $items = stableCursorPaginate($query, $filters);

        return InventoryItemCollection::make($items);
    }

    public function retrieveItem(InventoryItem $item, bool $includeItemable = false): InventoryItemResource
    {
        $this->assertOrganization($item->organization_id);

        if ($includeItemable) {
            $item->load('itemable');
        }

        return InventoryItemResource::make($item);
    }

    public function listLocations(InventoryLocationFilterData $filters, bool $includeExternal = false): InventoryLocationCollection
    {
        $query = InventoryLocation::query()->where('organization_id', $this->organizationId());

        if ($filters->ids) {
            $query->whereIn('id', $filters->ids);
        }

        if ($filters->externalType && $filters->externalId) {
            $query->where('external_type', $filters->externalType)
                ->whereIn('external_id', $filters->externalId);
        }

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        if ($includeExternal) {
            $query->with('external');
        }

        $locations = stableCursorPaginate($query, $filters);

        return InventoryLocationCollection::make($locations);
    }

    public function retrieveLocation(InventoryLocation $location, bool $includeExternal = false): InventoryLocationResource
    {
        $this->assertOrganization($location->organization_id);

        if ($includeExternal) {
            $location->load('external');
        }

        return InventoryLocationResource::make($location);
    }

    public function getItemStock(InventoryItem $item): ItemStockViewData
    {
        $this->assertOrganization($item->organization_id);

        $locations = InventoryStock::query()
            ->select('location_id')
            ->selectRaw('SUM(remaining) as remaining')
            ->where('item_id', $item->id)
            ->where('organization_id', $this->organizationId())
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

    public function getItemValue(InventoryItem $item, InventoryItemValueFilterData $filters): ItemValueViewData
    {
        $this->assertOrganization($item->organization_id);

        $query = InventoryStock::query()
            ->select(['location_id', 'currency_code'])
            ->selectRaw('SUM((remaining::numeric) * (unit_cost::numeric) + (cost_remainder::numeric)) as total_value_minor')
            ->where('item_id', $item->id)
            ->where('organization_id', $this->organizationId())
            ->where('remaining', '>', 0)
            ->whereNotNull('currency_code')
            ->groupBy('location_id', 'currency_code')
            ->orderBy('location_id')
            ->orderBy('currency_code');

        if ($filters->locationId) {
            $query->whereIn('location_id', $filters->locationId);
        }

        if ($filters->currencyCode) {
            $query->whereIn('currency_code', $filters->currencyCode);
        }

        $rows = $query->get();

        $totals = $rows
            ->groupBy('currency_code')
            ->map(function (Collection $group, string $currency): CurrencyValueViewData {
                $minor = $group->reduce(
                    fn (string $carry, mixed $row): string => bcadd(
                        $carry,
                        (string) data_get($row, 'total_value_minor', '0'),
                        0
                    ),
                    '0'
                );

                return new CurrencyValueViewData(
                    currencyCode: $currency,
                    totalValue: $this->masterInterface->fromMinor($minor, $currency),
                );
            })
            ->values();

        $locations = $rows
            ->groupBy('location_id')
            ->sortKeys()
            ->map(function (Collection $group, string $locationId): ItemValueLocationViewData {
                $values = $group
                    ->groupBy('currency_code')
                    ->map(function (Collection $currencyGroup, string $currency): CurrencyValueViewData {
                        $minor = $currencyGroup->reduce(
                            fn (string $carry, mixed $row): string => bcadd(
                                $carry,
                                (string) data_get($row, 'total_value_minor', '0'),
                                0
                            ),
                            '0'
                        );

                        return new CurrencyValueViewData(
                            currencyCode: $currency,
                            totalValue: $this->masterInterface->fromMinor($minor, $currency),
                        );
                    })
                    ->values();

                return new ItemValueLocationViewData(
                    locationId: $locationId,
                    values: $values,
                );
            })
            ->values();

        return new ItemValueViewData(
            itemId: $item->id,
            totals: $totals,
            locations: $locations,
        );
    }

    public function getLocationStock(InventoryLocation $location): LocationStockViewData
    {
        $this->assertOrganization($location->organization_id);

        $aggregatedStocks = InventoryStock::query()
            ->select('item_id')
            ->selectRaw('SUM(remaining) as remaining')
            ->where('location_id', $location->id)
            ->where('organization_id', $this->organizationId())
            ->where('remaining', '>', 0)
            ->groupBy('item_id')
            ->get();

        /** @var Collection<string, InventoryItem> $items */
        $items = InventoryItem::query()
            ->where('organization_id', $this->organizationId())
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

    public function getLocationValue(InventoryLocation $location, InventoryLocationValueFilterData $filters): LocationValueViewData
    {
        $this->assertOrganization($location->organization_id);

        $query = InventoryStock::query()
            ->select(['item_id', 'currency_code'])
            ->selectRaw('SUM((remaining::numeric) * (unit_cost::numeric) + (cost_remainder::numeric)) as total_value_minor')
            ->where('location_id', $location->id)
            ->where('organization_id', $this->organizationId())
            ->where('remaining', '>', 0)
            ->whereNotNull('currency_code')
            ->groupBy('item_id', 'currency_code')
            ->orderBy('item_id')
            ->orderBy('currency_code');

        if ($filters->itemId) {
            $query->whereIn('item_id', $filters->itemId);
        }

        if ($filters->currencyCode) {
            $query->whereIn('currency_code', $filters->currencyCode);
        }

        $rows = $query->get();

        $totals = $rows
            ->groupBy('currency_code')
            ->map(function (Collection $group, string $currency): CurrencyValueViewData {
                $minor = $group->reduce(
                    fn (string $carry, mixed $row): string => bcadd(
                        $carry,
                        (string) data_get($row, 'total_value_minor', '0'),
                        0
                    ),
                    '0'
                );

                return new CurrencyValueViewData(
                    currencyCode: $currency,
                    totalValue: $this->masterInterface->fromMinor($minor, $currency),
                );
            })
            ->values();

        $items = $rows
            ->groupBy('item_id')
            ->sortKeys()
            ->map(function (Collection $group, string $itemId): LocationValueItemViewData {
                $values = $group
                    ->groupBy('currency_code')
                    ->map(function (Collection $currencyGroup, string $currency): CurrencyValueViewData {
                        $minor = $currencyGroup->reduce(
                            fn (string $carry, mixed $row): string => bcadd(
                                $carry,
                                (string) data_get($row, 'total_value_minor', '0'),
                                0
                            ),
                            '0'
                        );

                        return new CurrencyValueViewData(
                            currencyCode: $currency,
                            totalValue: $this->masterInterface->fromMinor($minor, $currency),
                        );
                    })
                    ->values();

                return new LocationValueItemViewData(
                    itemId: $itemId,
                    values: $values,
                );
            })
            ->values();

        return new LocationValueViewData(
            locationId: $location->id,
            totals: $totals,
            items: $items,
        );
    }

    public function getItemLocationLots(
        InventoryItem $item,
        InventoryLocation $location,
        InventoryLotFilterData $filters
    ): ItemLocationLotsViewData {
        $this->assertOrganization($item->organization_id);
        $this->assertOrganization($location->organization_id);

        $strategy = $filters->strategy
            ?? $item->deduction_strategy
            ?? ($item->is_expirable ? DeductionStrategy::Fefo : DeductionStrategy::Fifo);

        $query = InventoryStock::query()
            ->where('item_id', $item->id)
            ->where('location_id', $location->id)
            ->where('organization_id', $this->organizationId())
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
            ))->values()
        );
    }

    public function listSummary(InventoryStockSummaryFilterData $filters): InventorySummaryCollection
    {
        $query = DB::table('inventory_stocks as stocks')
            ->join('inventory_items as items', 'items.id', '=', 'stocks.item_id')
            ->join('inventory_locations as locations', 'locations.id', '=', 'stocks.location_id')
            ->where('stocks.organization_id', $this->organizationId())
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

        $itemIds = collect($filters->itemId)->filter()->values();
        $locationIds = collect($filters->locationId)->filter()->values();

        if ($itemIds->isNotEmpty()) {
            $query->whereIn('stocks.item_id', $itemIds->all());
        }

        if ($locationIds->isNotEmpty()) {
            $query->whereIn('stocks.location_id', $locationIds->all());
        }

        $paginator = $query->cursorPaginate($filters->perPage, ['*'], 'cursor', $filters->cursor);

        return InventorySummaryCollection::make($paginator);
    }

    public function listExpiring(InventoryStockExpiringFilterData $filters): InventoryExpiringLotCollection
    {
        $query = InventoryStock::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_stocks.item_id')
            ->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stocks.location_id')
            ->where('inventory_stocks.organization_id', $this->organizationId())
            ->whereNull('inventory_items.deleted_at')
            ->whereNull('inventory_locations.deleted_at')
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

        $paginator = $query->cursorPaginate($filters->perPage, ['inventory_stocks.*'], 'cursor', $filters->cursor);

        return InventoryExpiringLotCollection::make($paginator);
    }

    public function listItemMovements(InventoryItem $item, InventoryMovementFilterData $filters): InventoryMovementCollection
    {
        $this->assertOrganization($item->organization_id);

        $query = InventoryMovement::query()
            ->with(['stock', 'location', 'unit', 'currency'])
            ->where('item_id', $item->id)
            ->where('organization_id', $this->organizationId());

        $this->applyMovementFilters($query, $filters);

        $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $query->cursorPaginate($filters->perPage, ['*'], 'cursor', $filters->cursor);

        return InventoryMovementCollection::make($paginator);
    }

    public function listLocationMovements(InventoryLocation $location, InventoryMovementFilterData $filters): InventoryMovementCollection
    {
        $this->assertOrganization($location->organization_id);

        $query = InventoryMovement::query()
            ->with(['stock', 'location', 'unit', 'currency'])
            ->where('location_id', $location->id)
            ->where('organization_id', $this->organizationId());

        $this->applyMovementFilters($query, $filters);

        $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $paginator = $query->cursorPaginate($filters->perPage, ['*'], 'cursor', $filters->cursor);

        return InventoryMovementCollection::make($paginator);
    }

    public function retrieveTransaction(InventoryTransaction $transaction): InventoryTransactionResource
    {
        $this->assertOrganization($transaction->organization_id);

        $transaction->load([
            'movements.stock.unit',
            'movements.stock.currency',
            'movements.location',
            'movements.unit',
            'movements.currency',
        ]);

        return InventoryTransactionResource::make($transaction);
    }

    public function listTransactions(InventoryTransactionFilterData $filters): InventoryTransactionCollection
    {
        $query = InventoryTransaction::query()->where('organization_id', $this->organizationId());

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

        $transactions = stableCursorPaginate($query, $filters);

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

    protected function applyMovementFilters(Builder $query, InventoryMovementFilterData $filters): void
    {
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

    protected function assertOrganization(string $organizationId): void
    {
        $currentOrganizationId = $this->organizationId();

        if ($organizationId !== $currentOrganizationId) {
            throw OrganizationScopeException::mismatch($currentOrganizationId, $organizationId);
        }
    }
}
