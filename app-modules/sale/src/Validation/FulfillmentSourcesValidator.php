<?php

declare(strict_types=1);

namespace Lahatre\Sale\Validation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Enums\StockLocationKind;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\ViewData\StockAvailabilityViewData;
use Lahatre\Inventory\ViewData\StockLotViewData;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Sale\Data\FulfillmentLineData;
use Lahatre\Sale\Data\FulfillmentSourceData;
use Lahatre\Sale\Data\PlannedFulfillmentLine;
use Lahatre\Sale\Enums\FulfillmentPurpose;
use Lahatre\Sale\Enums\FulfillmentStatus;
use Lahatre\Sale\Exceptions\SaleException;
use Lahatre\Sale\Models\FulfillmentLine;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleHold;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Shared\Support\Decimal;

/**
 * Checks where the lines of a sale leave from, against the sale, the warehouses and their stock,
 * with a fixed number of queries, and turns the request into lines ready to move. The caller holds
 * the sale's lock, so what is delivered and held cannot move meanwhile; recording the stock
 * movement checks the stock again under its own lock.
 */
final readonly class FulfillmentSourcesValidator
{
    public function __construct(
        private InventoryInterface $inventoryInterface,
        private MasterInterface $masterInterface,
    ) {}

    /**
     * @param  Collection<int, FulfillmentLineData>  $lines
     *
     * @throws ValidationException Naming each field of the request that is refused.
     * @throws SaleException If a warehouse, or the lots named, lack the stock asked.
     *
     * @return list<PlannedFulfillmentLine>
     */
    public function plan(Sale $sale, Collection $lines, FulfillmentPurpose $purpose): array
    {
        $organizationId = $sale->organization_id;
        /** @var Collection<string, SaleLine> $saleLines */
        $saleLines = SaleLine::query()
            ->where('organization_id', $organizationId)
            ->where('sale_id', $sale->id)
            ->orderBy('position')
            ->get()
            ->keyBy('id');
        /** @var Collection<string, CatalogItem> $items */
        $items = CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $saleLines->pluck('catalog_item_id')->unique()->values())
            ->with('inventoryItem')
            ->get()
            ->keyBy('id');
        $warehouses = $this->warehouses($organizationId, $lines);
        $sources = $this->sourceLocations($organizationId, $warehouses, $purpose);
        $delivered = $this->delivered($organizationId, $saleLines->keys());
        $held = $purpose->takesFromHold() ? $this->held($organizationId, $sale->id, $delivered['byLocation']) : collect();

        $errors = [];
        $planned = [];
        $listed = [];

        foreach ($lines as $i => $line) {
            $saleLine = $saleLines->get($line->saleLineId);
            if (!$saleLine instanceof SaleLine) {
                $errors["lines.{$i}.sale_line_id"] = (string) __('sale::validation.line_not_in_sale');

                continue;
            }
            if (isset($listed[$saleLine->id])) {
                $errors["lines.{$i}.sale_line_id"] = (string) __('sale::validation.line_listed_twice');

                continue;
            }
            $listed[$saleLine->id] = true;
            /** @var CatalogItem $item */
            $item = $items->get($saleLine->catalog_item_id);
            $isTracked = $this->isTracked($item);

            if ($purpose === FulfillmentPurpose::HoldConfirmation && !$isTracked) {
                $errors["lines.{$i}.sale_line_id"] = (string) __('sale::validation.nothing_to_hold');

                continue;
            }

            $lineTotal = 0;
            $seenWarehouses = [];
            foreach ($line->sources as $j => $source) {
                $path = "lines.{$i}.sources.{$j}";
                $warehouse = $this->sourceWarehouse($source, $warehouses, $purpose, $isTracked, $path, $seenWarehouses, $errors);
                if ($source->stockIds !== null && $source->stockIds !== [] && !$isTracked) {
                    $errors["{$path}.stock_ids"] = (string) __('sale::validation.lots_for_untracked_item');
                }
                $quantity = $this->toBase($source->quantity, $saleLine->display_unit_code);
                $lineTotal += $quantity;
                $sourceLocation = $warehouse instanceof StockLocation && $isTracked ? $sources->get($warehouse->id) : null;

                if ($purpose->takesFromHold() && $isTracked && $warehouse instanceof StockLocation) {
                    $heldHere = (int) $held->get("{$saleLine->id}:{$warehouse->id}", 0);
                    if (!$sourceLocation instanceof StockLocation || $heldHere === 0) {
                        $errors["{$path}.stock_location_id"] = (string) __('sale::validation.nothing_held_here');
                    } elseif ($quantity > $heldHere) {
                        $errors["{$path}.quantity"] = (string) __('sale::validation.quantity_over_held', ['held' => $this->fromBase($heldHere, $saleLine->display_unit_code), 'unit' => $saleLine->display_unit_code]);
                    }
                }

                $planned[] = new PlannedFulfillmentLine($saleLine, $item, $warehouse, $sourceLocation, $quantity, $source->stockIds ?: null, $isTracked, $path);
            }

            $remaining = $saleLine->quantity - (int) $delivered['byLine']->get($saleLine->id, 0);
            if ($purpose->coversWholeLines() && $lineTotal !== $saleLine->quantity) {
                $errors["lines.{$i}.sources"] = (string) __('sale::validation.sources_cover_line', ['quantity' => $this->fromBase($saleLine->quantity, $saleLine->display_unit_code), 'unit' => $saleLine->display_unit_code]);
            } elseif (!$purpose->coversWholeLines() && $lineTotal > $remaining) {
                $errors["lines.{$i}.sources"] = (string) __('sale::validation.sources_over_remaining', ['remaining' => $this->fromBase(max($remaining, 0), $saleLine->display_unit_code), 'unit' => $saleLine->display_unit_code]);
            }
        }

        if ($purpose->coversWholeLines()) {
            $planned = [...$planned, ...$this->unlisted($saleLines, $items, $listed, $purpose, $errors)];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->assertStockAvailable($planned);

        return $planned;
    }

    /**
     * At confirmation every tracked line is listed; a counter sale delivers an untracked line
     * left out whole, with no warehouse, and a bill-and-hold sale has nothing of it to set aside.
     *
     * @param  Collection<string, SaleLine>  $saleLines
     * @param  Collection<string, CatalogItem>  $items
     * @param  array<string, true>  $listed
     * @param  array<string, string>  $errors
     * @return list<PlannedFulfillmentLine>
     */
    private function unlisted(Collection $saleLines, Collection $items, array $listed, FulfillmentPurpose $purpose, array &$errors): array
    {
        $planned = [];
        $missing = [];

        foreach ($saleLines as $saleLine) {
            if (isset($listed[$saleLine->id])) {
                continue;
            }
            /** @var CatalogItem $item */
            $item = $items->get($saleLine->catalog_item_id);
            if ($this->isTracked($item)) {
                $missing[] = "{$saleLine->item_sku} ({$saleLine->display_unit_code})";
            } elseif ($purpose === FulfillmentPurpose::CounterConfirmation) {
                $planned[] = new PlannedFulfillmentLine($saleLine, $item, null, null, $saleLine->quantity, null, false, 'lines');
            }
        }

        if ($missing !== []) {
            $errors['lines'] = (string) __('sale::validation.lines_missing_sources', ['items' => implode(', ', $missing)]);
        }

        return $planned;
    }

    /**
     * @param  Collection<string, StockLocation>  $warehouses
     * @param  array<string, true>  $seenWarehouses
     * @param  array<string, string>  $errors
     */
    private function sourceWarehouse(FulfillmentSourceData $source, Collection $warehouses, FulfillmentPurpose $purpose, bool $isTracked, string $path, array &$seenWarehouses, array &$errors): ?StockLocation
    {
        if ($source->stockLocationId === null) {
            if ($isTracked) {
                $errors["{$path}.stock_location_id"] = (string) __('sale::validation.warehouse_required');
            }

            return null;
        }

        $key = $source->stockLocationId;
        if (isset($seenWarehouses[$key])) {
            $errors["{$path}.stock_location_id"] = (string) __('sale::validation.warehouse_listed_twice');

            return null;
        }
        $seenWarehouses[$key] = true;

        $warehouse = $warehouses->get($key);
        // goods already set aside may still leave a warehouse closed since
        $isUsable = $warehouse instanceof StockLocation
            && ($purpose->takesFromHold() || $warehouse->inventoryLocation?->is_active === true);
        if (!$isUsable) {
            $errors["{$path}.stock_location_id"] = (string) __('sale::validation.warehouse_unavailable');

            return null;
        }

        return $warehouse;
    }

    /**
     * @param  Collection<int, FulfillmentLineData>  $lines
     * @return Collection<string, StockLocation>
     */
    private function warehouses(string $organizationId, Collection $lines): Collection
    {
        $ids = $lines->flatMap(fn (FulfillmentLineData $line): Collection => $line->sources->pluck('stockLocationId'))
            ->filter(fn (?string $id): bool => is_string($id) && Str::isUuid($id))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return StockLocation::query()
            ->where('organization_id', $organizationId)
            ->where('kind', StockLocationKind::Warehouse)
            ->whereIn('id', $ids)
            ->with('inventoryLocation')
            ->get()
            ->keyBy('id');
    }

    /**
     * Where the stock leaves from, keyed by warehouse: the warehouse itself, or its customer hold
     * location, created at a bill-and-hold confirmation and only read afterwards.
     *
     * @param  Collection<string, StockLocation>  $warehouses
     * @return Collection<string, StockLocation>
     */
    private function sourceLocations(string $organizationId, Collection $warehouses, FulfillmentPurpose $purpose): Collection
    {
        if ($purpose === FulfillmentPurpose::HoldConfirmation) {
            // the transfer still leaves the warehouse; the hold location is where it lands
            return $warehouses;
        }

        if (!$purpose->takesFromHold() || $warehouses->isEmpty()) {
            return $warehouses;
        }

        return StockLocation::query()
            ->where('organization_id', $organizationId)
            ->whereIn('held_for_location_id', $warehouses->keys())
            ->with('inventoryLocation')
            ->get()
            ->keyBy('held_for_location_id');
    }

    /**
     * What completed fulfillments delivered, per sale line, and per sale line and warehouse.
     *
     * @param  Collection<int, string>  $saleLineIds
     * @return array{byLine: Collection<string, int>, byLocation: Collection<string, int>}
     */
    private function delivered(string $organizationId, Collection $saleLineIds): array
    {
        $rows = $saleLineIds->isEmpty() ? collect() : FulfillmentLine::query()
            ->where('organization_id', $organizationId)
            ->whereIn('sale_line_id', $saleLineIds)
            ->whereHas('fulfillment', fn (Builder $fulfillment): Builder => $fulfillment->where('status', FulfillmentStatus::Completed))
            ->get(['sale_line_id', 'stock_location_id', 'quantity']);

        return [
            'byLine'     => $rows->groupBy('sale_line_id')->map(fn (Collection $group): int => (int) $group->sum('quantity')),
            'byLocation' => $rows->groupBy(fn (FulfillmentLine $row): string => "{$row->sale_line_id}:{$row->stock_location_id}")
                ->map(fn (Collection $group): int => (int) $group->sum('quantity')),
        ];
    }

    /**
     * What is still set aside, per sale line and warehouse.
     *
     * @param  Collection<string, int>  $deliveredByLocation
     * @return Collection<string, int>
     */
    private function held(string $organizationId, string $saleId, Collection $deliveredByLocation): Collection
    {
        /** @var Collection<string, int> */
        return SaleHold::query()
            ->where('organization_id', $organizationId)
            ->where('sale_id', $saleId)
            ->get()
            ->mapWithKeys(function (SaleHold $hold) use ($deliveredByLocation): array {
                $key = "{$hold->sale_line_id}:{$hold->stock_location_id}";

                return [$key => max(0, $hold->quantity - $hold->released_quantity - (int) $deliveredByLocation->get($key, 0))];
            });
    }

    /**
     * Checks the lots named, then the quantity of each item at each location, once for all lines.
     *
     * @param  list<PlannedFulfillmentLine>  $planned
     *
     * @throws ValidationException
     * @throws SaleException
     */
    private function assertStockAvailable(array $planned): void
    {
        $moving = array_values(array_filter($planned, fn (PlannedFulfillmentLine $line): bool => $line->isTracked && $line->source instanceof StockLocation));
        if ($moving === []) {
            return;
        }

        /** @var Collection<int, HasInventoryItem> $movingItems */
        $movingItems = collect($moving)->map(fn (PlannedFulfillmentLine $line): CatalogItem => $line->catalogItem)->unique('id')->values();
        /** @var Collection<int, HasInventoryLocation> $locations */
        $locations = collect($moving)->map(fn (PlannedFulfillmentLine $line): ?StockLocation => $line->source)->unique('id')->values();
        $availability = $this->inventoryInterface->stockAvailability($movingItems, $locations);

        $errors = [];
        foreach ($moving as $line) {
            foreach ($line->stockIds ?? [] as $k => $stockId) {
                $lot = $availability->lots->get($stockId);
                if (!$lot instanceof StockLotViewData || $lot->itemId !== $line->catalogItem->id || $lot->locationId !== $line->source->id) {
                    $errors["{$line->path}.stock_ids.{$k}"] = (string) __('sale::validation.lot_unavailable');
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $shortages = [];
        foreach (collect($moving)->groupBy(fn (PlannedFulfillmentLine $line): string => "{$line->catalogItem->id}:{$line->source->id}") as $group) {
            /** @var PlannedFulfillmentLine $first */
            $first = $group->first();
            $asked = (int) $group->sum('quantity');
            $available = $availability->available($first->catalogItem->id, $first->source->id);
            if ($asked > $available) {
                $shortages[] = $this->shortage($first, $asked, $available);

                continue;
            }
            foreach ($group as $line) {
                $inLots = $this->inLots($availability, $line);
                if ($inLots !== null && $line->quantity > $inLots) {
                    $shortages[] = $this->shortage($line, $line->quantity, $inLots);
                }
            }
        }

        if ($shortages !== []) {
            throw SaleException::stockInsufficient($shortages);
        }
    }

    private function inLots(StockAvailabilityViewData $availability, PlannedFulfillmentLine $line): ?int
    {
        if ($line->stockIds === null) {
            return null;
        }

        return (int) collect($line->stockIds)->sum(function (string $stockId) use ($availability): int {
            $lot = $availability->lots->get($stockId);

            return $lot instanceof StockLotViewData ? $lot->remaining : 0;
        });
    }

    private function shortage(PlannedFulfillmentLine $line, int $asked, int $available): string
    {
        $unit = $line->saleLine->display_unit_code;

        return __('sale::exceptions.stock_shortage', [
            'item'      => $line->label(),
            'location'  => (string) $line->warehouse?->name,
            'asked'     => $this->fromBase($asked, $unit),
            'available' => $this->fromBase($available, $unit),
            'unit'      => $unit,
        ]);
    }

    private function isTracked(CatalogItem $item): bool
    {
        $inventoryItem = $item->inventoryItem;

        return $inventoryItem instanceof InventoryItem && $inventoryItem->stock_tracking_enabled;
    }

    private function toBase(int $quantity, string $unitCode): int
    {
        return (int) $this->masterInterface->convertUnitToBase((string) $quantity, $unitCode)['amount'];
    }

    private function fromBase(int $quantity, string $unitCode): string
    {
        return Decimal::normalize($this->masterInterface->convertUnitFromBase((string) $quantity, $unitCode)['amount']);
    }
}
