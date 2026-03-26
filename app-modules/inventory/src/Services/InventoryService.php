<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\DTO\MovementDataDTO;
use Lahatre\Inventory\DTO\TransactionDataDTO;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Validation\TransactionValidator;
use Lahatre\Master\Support\UnitCache;

class InventoryService implements InventoryInterface
{
    public function __construct(
        protected UnitCache $unitCache,
        protected TransactionValidator $transactionValidator
    ) {}

    public function createLocation(HasInventoryLocation $model): InventoryLocation
    {
        return ensure_transaction(function () use ($model) {
            $existing = InventoryLocation::where('external_type', $model->getMorphClass())
                ->where('external_id', $model->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return InventoryLocation::create([
                'external_type' => $model->getMorphClass(),
                'external_id'   => $model->getKey(),
                'is_active'     => true,
            ]);
        });
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
            $externalIds = $models->map(fn ($m) => $m->getKey())->toArray();

            $existingIds = InventoryLocation::where('external_type', $firstType)
                ->whereIn('external_id', $externalIds)
                ->pluck('external_id')
                ->toArray();

            $toInsert = $models
                ->reject(fn ($m) => in_array($m->getKey(), $existingIds))
                ->map(fn (HasInventoryLocation $m) => [
                    'id'            => (string) Str::uuid7(),
                    'external_type' => $firstType,
                    'external_id'   => $m->getKey(),
                    'is_active'     => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ])
                ->values()
                ->toArray();

            if (!empty($toInsert)) {
                InventoryLocation::insert($toInsert);
            }

            return InventoryLocation::where('external_type', $firstType)
                ->whereIn('external_id', $externalIds)
                ->get();
        });
    }

    public function createItem(HasInventoryItem $model): InventoryItem
    {
        return ensure_transaction(function () use ($model) {
            $existing = InventoryItem::where('itemable_type', $model->getMorphClass())
                ->where('itemable_id', $model->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return InventoryItem::create([
                'itemable_type'  => $model->getMorphClass(),
                'itemable_id'    => $model->getKey(),
                'is_active'      => true,
                'base_unit_code' => $this->unitCache->getBaseUnit($model->getUnitGroupId())->code,
            ]);
        });
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
            $externalIds = $models->map(fn ($m) => $m->getKey())->toArray();

            $existingIds = InventoryItem::where('itemable_type', $firstType)
                ->whereIn('itemable_id', $externalIds)
                ->pluck('itemable_id')
                ->toArray();

            $toInsert = $models
                ->reject(fn ($m) => in_array($m->getKey(), $existingIds))
                ->map(fn (HasInventoryItem $m) => [
                    'id'             => (string) Str::uuid7(),
                    'itemable_type'  => $firstType,
                    'itemable_id'    => $m->getKey(),
                    'is_active'      => true,
                    'base_unit_code' => $this->unitCache->getBaseUnit($m->getUnitGroupId())->code,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ])
                ->values()
                ->toArray();

            if (!empty($toInsert)) {
                InventoryItem::insert($toInsert);
            }

            return InventoryItem::where('itemable_type', $firstType)
                ->whereIn('itemable_id', $externalIds)
                ->get();
        });
    }

    public function updateLocation(string $id, array $data): InventoryLocation
    {
        return ensure_transaction(function () use ($id, $data) {
            $location = InventoryLocation::findOrFail($id);
            $location->fill([
                'is_active' => $data['is_active'] ?? $location->is_active,
            ]);
            $location->save();

            return $location;
        });
    }

    public function updateItem(string $id, array $data): InventoryItem
    {
        validator($data, ['deduction_strategy' => Rule::enum(DeductionStrategy::class)])->validate();

        return ensure_transaction(function () use ($id, $data) {
            $item = InventoryItem::findOrFail($id);
            $item->fill([
                'sku'                => $data['sku'] ?? $item->sku,
                'is_active'          => $data['is_active'] ?? $item->is_active,
                'deduction_strategy' => $data['deduction_strategy'] ?? $item->deduction_strategy,
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
        [$validatedData, $lookups] = $this->transactionValidator->validate($data);

        $transaction = TransactionDataDTO::fromArray($validatedData);

        return ensure_transaction(function () use ($transaction, $lookups) {
            $items = $lookups['items'];

            $tx = InventoryTransaction::create([
                'reference_type'   => $transaction->reference_type,
                'reference_id'     => $transaction->reference_id,
                'transaction_type' => $transaction->transaction_type,
                'metadata'         => $transaction->metadata,
            ]);

            match ($transaction->transaction_type) {
                TransactionType::In         => $this->processInMovements($tx, $transaction, $items),
                TransactionType::Out        => $this->processOutMovements($tx, $transaction, $items),
                TransactionType::Transfer   => $this->processTransferMovements($tx, $transaction, $items),
                TransactionType::Adjustment => $this->processAdjustmentMovements($tx, $transaction, $items),
            };

            return $tx->load('movements');
        });
    }

    protected function processInMovements(
        InventoryTransaction $tx,
        TransactionDataDTO $dto,
        Collection $items
    ): void {
        foreach ($dto->movements as $m) {
            $item = $items->get($m->item_id);
            $qtyInBase = convertUnit($m->quantity, $m->unit_code, $item->base_unit_code);

            $stock = InventoryStock::create([
                'item_id'         => $m->item_id,
                'location_id'     => $m->location_id,
                'unit_cost'       => $m->unit_cost,
                'currency_code'   => $m->currency_code,
                'quantity'        => (int) $qtyInBase,
                'remaining'       => (int) $qtyInBase,
                'unit_code'       => $item->base_unit_code,
                'expiration_date' => $m->expiration_date,
                'metadata'        => $m->metadata,
            ]);

            InventoryMovement::create([
                'movement_type'   => MovementType::In,
                'transaction_id'  => $tx->id,
                'item_id'         => $m->item_id,
                'location_id'     => $m->location_id,
                'stock_id'        => $stock->id,
                'quantity'        => (int) $qtyInBase,
                'unit_code'       => $item->base_unit_code,
                'unit_cost'       => $m->unit_cost,
                'currency_code'   => $m->currency_code,
                'expiration_date' => $m->expiration_date,
                'metadata'        => $m->metadata,
            ]);
        }
    }

    protected function processOutMovements(
        InventoryTransaction $tx,
        TransactionDataDTO $dto,
        Collection $items
    ): void {
        foreach ($dto->movements as $m) {
            $item = $items->get($m->item_id);
            $qtyToDeduct = convertUnit($m->quantity, $m->unit_code, $item->base_unit_code);

            $this->applyDeduction($tx, $m, $item, $qtyToDeduct);
        }
    }

    protected function applyDeduction(
        InventoryTransaction $tx,
        MovementDataDTO $m,
        InventoryItem $item,
        string $qtyToDeduct,
        ?Collection $stocks = null
    ): Collection {
        $stocks ??= $this->resolveStocksForDeduction($m, $item);

        $totalAvailable = (string) $stocks->sum('remaining');

        if (bccomp($qtyToDeduct, $totalAvailable, 10) > 0) {
            throw new InsufficientStockException(
                $m->item_id,
                $m->location_id,
                $m->quantity,
                convertUnit($totalAvailable, $item->base_unit_code, $m->unit_code),
                $m->unit_code
            );
        }

        $remainingToDeduct = $qtyToDeduct;
        /** @var Collection<int, InventoryMovement> $movements */
        $movements = collect();

        foreach ($stocks as $stock) {
            if (bccomp($remainingToDeduct, '0', 10) <= 0) {
                break;
            }

            $stockRemaining = (string) $stock->remaining;
            $deduction = bccomp($remainingToDeduct, $stockRemaining, 10) >= 0
                ? $stockRemaining
                : $remainingToDeduct;

            $stock->remaining = (int) bcsub($stockRemaining, $deduction, 0);
            $stock->save();

            $movements->push(InventoryMovement::create([
                'movement_type'   => MovementType::Out,
                'transaction_id'  => $tx->id,
                'item_id'         => $m->item_id,
                'location_id'     => $m->location_id,
                'stock_id'        => $stock->id,
                'quantity'        => (int) $deduction,
                'unit_code'       => $item->base_unit_code,
                'unit_cost'       => $stock->unit_cost,
                'currency_code'   => $stock->currency_code,
                'expiration_date' => $stock->expiration_date,
                'metadata'        => $m->metadata,
            ]));

            $remainingToDeduct = bcsub($remainingToDeduct, $deduction, 10);
        }

        return $movements;
    }

    protected function processTransferMovements(
        InventoryTransaction $tx,
        TransactionDataDTO $dto,
        Collection $items
    ): void {
        /** @var Collection<string, Collection<int, MovementDataDTO>> $groupedByItem */
        $groupedByItem = $dto->movements->groupBy('item_id');

        foreach ($groupedByItem as $itemId => $movements) {
            $item = $items->get($itemId);
            /** @var Collection<int, MovementDataDTO> $outMovements */
            $outMovements = $movements->filter(fn (MovementDataDTO $m): bool => $m->type === MovementType::Out);
            /** @var Collection<int, MovementDataDTO> $inMovements */
            $inMovements = $movements->filter(fn (MovementDataDTO $m): bool => $m->type === MovementType::In);

            // 1. Collect all deducted batches from all source locations (the "pool")
            /** @var Collection<int, InventoryMovement> $poolOfDeductedBatches */
            $poolOfDeductedBatches = collect();
            foreach ($outMovements as $outM) {
                $qtyInBase = convertUnit($outM->quantity, $outM->unit_code, $item->base_unit_code);
                /** @var Collection<int, InventoryMovement> $deductedMovements */
                $deductedMovements = $this->applyDeduction($tx, $outM, $item, $qtyInBase);
                $poolOfDeductedBatches = $poolOfDeductedBatches->concat($deductedMovements);
            }

            // 2. Distribute these batches to the destination locations
            foreach ($inMovements as $inM) {
                $remainingToFill = convertUnit($inM->quantity, $inM->unit_code, $item->base_unit_code);

                while (bccomp($remainingToFill, '0', 10) > 0 && $poolOfDeductedBatches->isNotEmpty()) {
                    /** @var InventoryMovement $currentBatch */
                    $currentBatch = $poolOfDeductedBatches->first();
                    $batchAvailable = (string) $currentBatch->quantity;

                    $take = bccomp($remainingToFill, $batchAvailable, 10) >= 0
                        ? $batchAvailable
                        : $remainingToFill;

                    $stock = InventoryStock::create([
                        'item_id'         => $item->id,
                        'location_id'     => $inM->location_id,
                        'unit_cost'       => $currentBatch->unit_cost,
                        'currency_code'   => $currentBatch->currency_code,
                        'quantity'        => (int) $take,
                        'remaining'       => (int) $take,
                        'unit_code'       => $item->base_unit_code,
                        'expiration_date' => $currentBatch->expiration_date,
                        'metadata'        => $inM->metadata,
                    ]);

                    InventoryMovement::create([
                        'movement_type'   => MovementType::In,
                        'transaction_id'  => $tx->id,
                        'item_id'         => $item->id,
                        'location_id'     => $inM->location_id,
                        'stock_id'        => $stock->id,
                        'quantity'        => (int) $take,
                        'unit_code'       => $item->base_unit_code,
                        'unit_cost'       => $currentBatch->unit_cost,
                        'currency_code'   => $currentBatch->currency_code,
                        'expiration_date' => $currentBatch->expiration_date,
                        'metadata'        => $inM->metadata,
                    ]);

                    $remainingToFill = bcsub($remainingToFill, $take, 10);

                    if (bccomp($take, $batchAvailable, 10) === 0) {
                        $poolOfDeductedBatches->shift();
                    } else {
                        // Partially used batch: update quantity in the pool for the next 'IN' movement (no persistence in DB)
                        $currentBatch->quantity = (int) bcsub($batchAvailable, $take, 0);
                    }
                }

                if (bccomp($remainingToFill, '0', 10) > 0) {
                    throw new \RuntimeException("Transfer imbalance detected for item {$itemId}: Destination location {$inM->location_id} could not be fully filled from source stocks.");
                }
            }

            if ($poolOfDeductedBatches->isNotEmpty()) {
                throw new \RuntimeException("Transfer imbalance detected for item {$itemId}: Source stocks were not fully distributed to destinations.");
            }
        }
    }

    protected function processAdjustmentMovements(
        InventoryTransaction $tx,
        TransactionDataDTO $dto,
        Collection $items
    ): void {
        foreach ($dto->movements as $m) {
            $item = $items->get($m->item_id);
            $targetQtyInBase = convertUnit($m->quantity, $m->unit_code, $item->base_unit_code);

            // 1. Lock all relevant stocks for this item/location to avoid race conditions
            $stocks = $this->resolveStocksForDeduction($m, $item);

            $currentQtyInBase = (string) $stocks->sum('remaining');
            $delta = bcsub($targetQtyInBase, $currentQtyInBase, 10);
            $cmp = bccomp($delta, '0', 10);

            if ($cmp > 0) {
                // Adjustment UP: create a new stock record with provided cost or default value
                $qtyToAdd = $delta;

                $unitCost = $m->unit_cost ?? 0;
                $currencyCode = $m->currency_code ?? null;

                $stock = InventoryStock::create([
                    'item_id'         => $m->item_id,
                    'location_id'     => $m->location_id,
                    'unit_cost'       => $unitCost,
                    'currency_code'   => $currencyCode,
                    'quantity'        => (int) $qtyToAdd,
                    'remaining'       => (int) $qtyToAdd,
                    'unit_code'       => $item->base_unit_code,
                    'expiration_date' => $m->expiration_date,
                    'metadata'        => $m->metadata,
                ]);

                InventoryMovement::create([
                    'movement_type'   => MovementType::In,
                    'transaction_id'  => $tx->id,
                    'item_id'         => $m->item_id,
                    'location_id'     => $m->location_id,
                    'stock_id'        => $stock->id,
                    'quantity'        => (int) $qtyToAdd,
                    'unit_code'       => $item->base_unit_code,
                    'unit_cost'       => $unitCost,
                    'currency_code'   => $currencyCode,
                    'expiration_date' => $m->expiration_date,
                    'metadata'        => $m->metadata,
                ]);
            } elseif ($cmp < 0) {
                // Adjustment DOWN: use existing locked stocks for deduction
                $this->applyDeduction($tx, $m, $item, bcsub('0', $delta, 10), $stocks);
            } else {
                throw new \Exception('The target quantity is already the current stock.');
            }
        }
    }

    protected function resolveStocksForDeduction(MovementDataDTO $m, InventoryItem $item): Collection
    {
        $strategy = $m->strategy
            ?? $item->deduction_strategy
            ?? DeductionStrategy::tryFrom((string) config('inventory.default_strategy'))
            ?? DeductionStrategy::Fifo;

        $query = InventoryStock::where('item_id', $m->item_id)
            ->where('location_id', $m->location_id)
            ->where('remaining', '>', 0)
            ->lockForUpdate();

        return match ($strategy) {
            DeductionStrategy::Fifo => $query->orderBy('created_at', 'asc')->get(),
            DeductionStrategy::Fefo => $query->orderByRaw('expiration_date ASC NULLS LAST')
                ->orderBy('created_at', 'asc')->get(),
            DeductionStrategy::Manual => $query->whereIn('id', $m->stock_ids)
                ->orderBy('created_at', 'asc')->get(),
        };
    }
}
