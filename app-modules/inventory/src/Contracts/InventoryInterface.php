<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Contracts;

use Illuminate\Support\Collection;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryTransaction;

interface InventoryInterface
{
    public function createLocation(HasInventoryLocation $model): InventoryLocation;

    /**
     * @param  array<int, HasInventoryLocation>|Collection<int, HasInventoryLocation>  $models
     * @return Collection<int, InventoryLocation>
     */
    public function createManyLocations(array|Collection $models): Collection;

    public function createItem(HasInventoryItem $model): InventoryItem;

    /**
     * @param  array<int, HasInventoryItem>|Collection<int, HasInventoryItem>  $models
     * @return Collection<int, InventoryItem>
     */
    public function createManyItems(array|Collection $models): Collection;

    /**
     * @param  array{is_active?: bool}  $data
     */
    public function updateLocation(string $id, array $data): InventoryLocation;

    /**
     * @param  array{sku?: string, is_active?: bool}  $data
     */
    public function updateItem(string $id, array $data): InventoryItem;

    public function deleteLocation(string $id): void;

    public function deleteItem(string $id): void;

    /**
     * @param array{
     *     reference_type: string,
     *     reference_id: string,
     *     transaction_type: TransactionType,
     *     metadata?: array,
     *     movements: array<int, array{
     *         item_id: string,
     *         location_id: string,
     *         quantity: int,
     *         unit_code: string,
     *         unit_cost: int,
     *         currency_code: string,
     *         peremption_date?: string|\DateTimeInterface
     *     }>
     * } $data
     */
    public function recordTransaction(array $data): InventoryTransaction;
}
