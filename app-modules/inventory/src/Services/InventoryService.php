<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryTransaction;

class InventoryService implements InventoryInterface
{
    public function createLocation(HasInventoryLocation $model): InventoryLocation
    {
        return ensure_transaction(fn () => InventoryLocation::firstOrCreate([
            'external_type' => $model->getMorphClass(),
            'external_id'   => $model->getKey(),
        ]));
    }

    /**
     * @param  array<int, HasInventoryLocation>|Collection<int, HasInventoryLocation>  $models
     */
    public function createManyLocations(array|Collection $models): Collection
    {
        return ensure_transaction(function () use ($models) {
            $models = collect($models);

            if ($models->isEmpty()) {
                return collect();
            }

            $firstType = $models->first()->getMorphClass();

            if ($models->some(fn ($m): bool => $m->getMorphClass() !== $firstType)) {
                throw new \InvalidArgumentException('All models must be of the same type, mixed types given.');
            }

            $now = now();
            $externalIds = [];

            $data = $models->map(function (HasInventoryLocation $model) use ($now, $firstType, &$externalIds): array {
                $externalIds[] = $model->getKey();

                return [
                    'id'            => (string) Str::uuid7(),
                    'external_type' => $firstType,
                    'external_id'   => $model->getKey(),
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            })->toArray();

            // Note: Batch operations bypass Model events.
            InventoryLocation::upsert($data, ['external_type', 'external_id'], ['updated_at']);

            return InventoryLocation::where('external_type', $firstType)
                ->whereIn('external_id', $externalIds)
                ->get();
        });
    }

    public function createItem(HasInventoryItem $model): InventoryItem
    {
        return ensure_transaction(fn () => InventoryItem::firstOrCreate([
            'itemable_type' => $model->getMorphClass(),
            'itemable_id'   => $model->getKey(),
        ]));
    }

    /**
     * @param  array<int, HasInventoryItem>|Collection<int, HasInventoryItem>  $models
     */
    public function createManyItems(array|Collection $models): Collection
    {
        return ensure_transaction(function () use ($models) {
            $models = collect($models);

            if ($models->isEmpty()) {
                return collect();
            }

            $firstType = $models->first()->getMorphClass();

            if ($models->some(fn ($m): bool => $m->getMorphClass() !== $firstType)) {
                throw new \InvalidArgumentException('All models must be of the same type, mixed types given.');
            }

            $now = now();
            $externalIds = [];

            $data = $models->map(function (HasInventoryItem $model) use ($now, $firstType, &$externalIds): array {
                $externalIds[] = $model->getKey();

                return [
                    'id'            => (string) Str::uuid7(),
                    'itemable_type' => $firstType,
                    'itemable_id'   => $model->getKey(),
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            })->toArray();

            // Note: Batch operations bypass Model events.
            InventoryItem::upsert($data, ['itemable_type', 'itemable_id'], ['updated_at']);

            return InventoryItem::where('itemable_type', $firstType)
                ->whereIn('itemable_id', $externalIds)
                ->get();
        });
    }

    public function updateLocation(string $id, array $data): InventoryLocation
    {
        return ensure_transaction(function () use ($id) {
            $location = InventoryLocation::findOrFail($id);
            $location->fill([
                //
            ]);
            $location->save();

            return $location;
        });
    }

    public function updateItem(string $id, array $data): InventoryItem
    {
        return ensure_transaction(function () use ($id) {
            $item = InventoryItem::findOrFail($id);
            $item->fill([
                //
            ]);
            $item->save();

            return $item;
        });
    }

    public function deleteLocation(string $id): void
    {
        ensure_transaction(function () use ($id): void {
            InventoryLocation::findOrFail($id)->delete();
        });
    }

    public function deleteItem(string $id): void
    {
        ensure_transaction(function () use ($id): void {
            InventoryItem::findOrFail($id)->delete();
        });
    }

    public function recordTransaction(array $data): InventoryTransaction
    {
        // TODO: Implement complex transaction logic with movements and stock updates
        return InventoryTransaction::create($data);
    }
}
