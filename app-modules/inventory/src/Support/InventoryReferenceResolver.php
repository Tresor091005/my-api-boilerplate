<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Master\Support\UnitCache;

class InventoryReferenceResolver
{
    public function __construct(
        protected UnitCache $unitCache,
    ) {}

    /**
     * @param  Collection<int, HasInventoryItem>  $models
     * @return Collection<int, InventoryItem>
     */
    public function ensureInventoryItems(Collection $models): Collection
    {
        if ($models->isEmpty()) {
            return collect();
        }

        $groupedModels = $models
            ->unique(fn (HasInventoryItem $model): string => $model->getMorphClass().':'.(string) $model->getKey())
            ->groupBy(fn (HasInventoryItem $model): string => $model->getMorphClass());

        $resolvedItems = collect();

        foreach ($groupedModels as $morphClass => $typedModels) {
            $externalIds = $typedModels
                ->map(fn (HasInventoryItem $model): string => (string) $model->getKey())
                ->values();

            $existingItems = InventoryItem::query()
                ->where('itemable_type', $morphClass)
                ->whereIn('itemable_id', $externalIds)
                ->get()
                ->keyBy('itemable_id');

            $missingModels = $typedModels
                ->reject(fn (HasInventoryItem $model): bool => $existingItems->has((string) $model->getKey()))
                ->values();

            if ($missingModels->isNotEmpty()) {
                $now = now();

                InventoryItem::query()->insert(
                    $missingModels
                        ->map(fn (HasInventoryItem $model): array => [
                            'id'             => (string) Str::uuid7(),
                            'itemable_type'  => $model->getMorphClass(),
                            'itemable_id'    => (string) $model->getKey(),
                            'is_active'      => true,
                            'base_unit_code' => $this->unitCache->getBaseUnit($model->getUnitGroupId())->code,
                            'created_at'     => $now,
                            'updated_at'     => $now,
                        ])
                        ->all()
                );

                $existingItems = InventoryItem::query()
                    ->where('itemable_type', $morphClass)
                    ->whereIn('itemable_id', $externalIds)
                    ->get()
                    ->keyBy('itemable_id');
            }

            $resolvedItems = $resolvedItems->merge(
                $typedModels->map(fn (HasInventoryItem $model): InventoryItem => $existingItems->get((string) $model->getKey()))
            );
        }

        return $resolvedItems->values();
    }

    /**
     * @param  Collection<int, HasInventoryLocation>  $models
     * @return Collection<int, InventoryLocation>
     */
    public function ensureInventoryLocations(Collection $models): Collection
    {
        if ($models->isEmpty()) {
            return collect();
        }

        $groupedModels = $models
            ->unique(fn (HasInventoryLocation $model): string => $model->getMorphClass().':'.(string) $model->getKey())
            ->groupBy(fn (HasInventoryLocation $model): string => $model->getMorphClass());

        $resolvedLocations = collect();

        foreach ($groupedModels as $morphClass => $typedModels) {
            $externalIds = $typedModels
                ->map(fn (HasInventoryLocation $model): string => (string) $model->getKey())
                ->values();

            $existingLocations = InventoryLocation::query()
                ->where('external_type', $morphClass)
                ->whereIn('external_id', $externalIds)
                ->get()
                ->keyBy('external_id');

            $missingModels = $typedModels
                ->reject(fn (HasInventoryLocation $model): bool => $existingLocations->has((string) $model->getKey()))
                ->values();

            if ($missingModels->isNotEmpty()) {
                $now = now();

                InventoryLocation::query()->insert(
                    $missingModels
                        ->map(fn (HasInventoryLocation $model): array => [
                            'id'            => (string) Str::uuid7(),
                            'external_type' => $model->getMorphClass(),
                            'external_id'   => (string) $model->getKey(),
                            'is_active'     => true,
                            'created_at'    => $now,
                            'updated_at'    => $now,
                        ])
                        ->all()
                );

                $existingLocations = InventoryLocation::query()
                    ->where('external_type', $morphClass)
                    ->whereIn('external_id', $externalIds)
                    ->get()
                    ->keyBy('external_id');
            }

            $resolvedLocations = $resolvedLocations->merge(
                $typedModels->map(fn (HasInventoryLocation $model): InventoryLocation => $existingLocations->get((string) $model->getKey()))
            );
        }

        return $resolvedLocations->values();
    }

    public function preprocessTransactionData(array $data): array
    {
        $movements = collect($data['movements'] ?? []);

        $resolvedItems = $this->ensureInventoryItems(
            $movements
                ->map(fn (mixed $movement): mixed => is_array($movement) ? ($movement['item'] ?? $movement['item_id'] ?? null) : null)
                ->filter(fn (mixed $reference): bool => $reference instanceof HasInventoryItem)
                ->values()
        )->keyBy(fn (InventoryItem $item): string => $item->itemable_type.':'.$item->itemable_id);

        $resolvedLocations = $this->ensureInventoryLocations(
            $movements
                ->map(fn (mixed $movement): mixed => is_array($movement) ? ($movement['location'] ?? $movement['location_id'] ?? null) : null)
                ->filter(fn (mixed $reference): bool => $reference instanceof HasInventoryLocation)
                ->values()
        )->keyBy(fn (InventoryLocation $location): string => $location->external_type.':'.$location->external_id);

        $movements = $movements->map(function (mixed $movement) use ($resolvedItems, $resolvedLocations): mixed {
            if (!is_array($movement)) {
                return $movement;
            }

            $itemReference = $movement['item'] ?? $movement['item_id'] ?? null;
            if ($itemReference instanceof HasInventoryItem) {
                $itemKey = $itemReference->getMorphClass().':'.(string) $itemReference->getKey();
                $movement['item_id'] = $resolvedItems->get($itemKey)?->id;
                unset($movement['item']);
            }

            $locationReference = $movement['location'] ?? $movement['location_id'] ?? null;
            if ($locationReference instanceof HasInventoryLocation) {
                $locationKey = $locationReference->getMorphClass().':'.(string) $locationReference->getKey();
                $movement['location_id'] = $resolvedLocations->get($locationKey)?->id;
                unset($movement['location']);
            }

            return $movement;
        })->values()->all();

        $data['movements'] = $movements;

        return $data;
    }
}
