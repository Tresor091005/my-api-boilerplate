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
    public function updateLocation(HasInventoryLocation $model, array $data): InventoryLocation;

    /**
     * @param  array{sku?: string, is_active?: bool, deduction_strategy?: string}  $data
     */
    public function updateItem(HasInventoryItem $model, array $data): InventoryItem;

    public function deleteLocation(HasInventoryLocation $model): void;

    public function deleteItem(HasInventoryItem $model): void;

    /**
     * @param array{
     *     idempotency_key: string,
     *     reference_type: string,
     *     reference_id: string,
     *     transaction_type: TransactionType|string,
     *     metadata?: array,
     *     movements: array<int, array{
     *         item?: HasInventoryItem,
     *         item_id?: string,
     *         location?: HasInventoryLocation,
     *         location_id?: string,
     *         quantity: int|float|string,
     *         unit_code: string,
     *         unit_cost?: int,
     *         currency_code?: string,
     *         expiration_date?: string|\DateTimeInterface,
     *         metadata?: array,
     *         stock_metadata?: array
     *     }>
     * } $data
     * @param  array<int|string, mixed>  $with
     */
    public function recordTransaction(array $data, array $with = ['movements']): InventoryTransaction;

    public function reverseTransaction(string $originalTransactionId, ?array $metadata = null, array $with = ['movements']): InventoryTransaction;
}
