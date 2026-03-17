<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\DTO\MovementDataDTO;
use Lahatre\Inventory\DTO\TransactionDataDTO;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Exceptions\TransferBalanceException;
use Lahatre\Inventory\Exceptions\UnitGroupMismatchException;
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
        protected UnitCache $unitCache
    ) {}

    public function createLocation(HasInventoryLocation $model): InventoryLocation
    {
        return ensure_transaction(fn () => InventoryLocation::firstOrCreate(
            [
                'external_type' => $model->getMorphClass(),
                'external_id'   => $model->getKey(),
            ],
            [
                'is_active' => true,
            ]
        ));
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
                    'is_active'     => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            })->toArray();

            // Note: Batch operations bypass Model events.
            // TODO: investigate softDelete is working
            InventoryLocation::upsert($data, ['external_type', 'external_id'], ['updated_at']);

            return InventoryLocation::where('external_type', $firstType)
                ->whereIn('external_id', $externalIds)
                ->get();
        });
    }

    public function createItem(HasInventoryItem $model): InventoryItem
    {
        $baseUnitCode = $this->unitCache->getBaseUnit($model->getUnitGroupId())->code;

        return ensure_transaction(fn () => InventoryItem::firstOrCreate(
            [
                'itemable_type' => $model->getMorphClass(),
                'itemable_id'   => $model->getKey(),
            ],
            [
                'is_active'      => true,
                'base_unit_code' => $baseUnitCode,
            ]
        ));
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
                    'id'             => (string) Str::uuid7(),
                    'itemable_type'  => $firstType,
                    'itemable_id'    => $model->getKey(),
                    'is_active'      => true,
                    'base_unit_code' => $this->unitCache->getBaseUnit($model->getUnitGroupId())->code,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            })->toArray();

            // Note: Batch operations bypass Model events.
            // We only update 'updated_at' to avoid overriding 'base_unit_code' if record exists.
            InventoryItem::upsert($data, ['itemable_type', 'itemable_id'], ['updated_at']);

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
        $validatedData = Validator::make($data, TransactionValidator::rules())->validate();

        $transaction = TransactionDataDTO::fromArray($validatedData);

        return ensure_transaction(function () use ($transaction) {
            $items = InventoryItem::whereIn('id', $transaction->movements->pluck('item_id'))->get();

            $this->validateMovementDirections($transaction);
            $this->validateUnitGroups($transaction, $items);

            if ($transaction->transaction_type === TransactionType::Transfer) {
                $this->validateTransferBalance($transaction, $items);
            }

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

    protected function processInMovements(InventoryTransaction $tx, TransactionDataDTO $dto, Collection $items): void
    {
        foreach ($dto->movements as $m) {
            $item = $items->firstWhere('id', $m->item_id);
            $qtyInBase = convertUnit($m->quantity, $m->unit_code, $item->base_unit_code);

            $stock = InventoryStock::create([
                'item_id'         => $m->item_id,
                'location_id'     => $m->location_id,
                'unit_cost'       => $m->unit_cost,
                'currency_code'   => $m->currency_code,
                'quantity'        => (int) $qtyInBase,
                'remaining'       => (int) $qtyInBase,
                'unit_code'       => $item->base_unit_code,
                'peremption_date' => $m->peremption_date,
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
                'peremption_date' => $m->peremption_date,
                'metadata'        => $m->metadata,
            ]);
        }
    }

    protected function processOutMovements(InventoryTransaction $tx, TransactionDataDTO $dto, Collection $items): void
    {
        foreach ($dto->movements as $m) {
            $item = $items->firstWhere('id', $m->item_id);
            $qtyToDeduct = convertUnit($m->quantity, $m->unit_code, $item->base_unit_code);

            $this->applyDeduction($tx, $m, $item, $qtyToDeduct);
        }
    }

    protected function applyDeduction(InventoryTransaction $tx, MovementDataDTO $m, InventoryItem $item, string $qtyToDeduct): void
    {
        $strategy = $m->strategy
            ?? $item->deduction_strategy
            ?? DeductionStrategy::tryFrom((string) config('inventory.default_strategy'))
            ?? DeductionStrategy::Fifo;

        $stocks = $this->getStocksForDeduction($m, $strategy);

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

            InventoryMovement::create([
                'movement_type'  => MovementType::Out,
                'transaction_id' => $tx->id,
                'item_id'        => $m->item_id,
                'location_id'    => $m->location_id,
                'stock_id'       => $stock->id,
                'quantity'       => (int) $deduction,
                'unit_code'      => $item->base_unit_code,
                'unit_cost'      => $stock->unit_cost,
                'currency_code'  => $stock->currency_code,
            ]);

            $remainingToDeduct = bcsub($remainingToDeduct, $deduction, 10);
        }
    }

    protected function getStocksForDeduction(
        MovementDataDTO $m,
        DeductionStrategy $strategy
    ): Collection {
        $query = InventoryStock::where('item_id', $m->item_id)
            ->where('location_id', $m->location_id)
            ->where('remaining', '>', 0)
            ->lockForUpdate();

        return match ($strategy) {
            DeductionStrategy::Fifo => $query->orderBy('created_at', 'asc')->get(),
            DeductionStrategy::Fefo => $query->orderByRaw('peremption_date ASC NULLS LAST')
                ->orderBy('created_at', 'asc')->get(),
            DeductionStrategy::Manual => $query->whereIn('id', $m->stock_ids)->get(),
        };
    }

    protected function processTransferMovements(InventoryTransaction $tx, TransactionDataDTO $dto, Collection $items): void
    {
        foreach ($dto->movements as $m) {
            $item = $items->firstWhere('id', $m->item_id);
            $qtyInBase = convertUnit($m->quantity, $m->unit_code, $item->base_unit_code);

            if ($m->type === MovementType::Out) {
                $this->applyDeduction($tx, $m, $item, $qtyInBase);
            } else {
                $stock = InventoryStock::create([
                    'item_id'         => $m->item_id,
                    'location_id'     => $m->location_id,
                    'unit_cost'       => $m->unit_cost,
                    'currency_code'   => $m->currency_code,
                    'quantity'        => (int) $qtyInBase,
                    'remaining'       => (int) $qtyInBase,
                    'unit_code'       => $item->base_unit_code,
                    'peremption_date' => $m->peremption_date,
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
                    'peremption_date' => $m->peremption_date,
                    'metadata'        => $m->metadata,
                ]);
            }
        }
    }

    protected function processAdjustmentMovements(InventoryTransaction $tx, TransactionDataDTO $dto, Collection $items): void
    {
        foreach ($dto->movements as $m) {
            $item = $items->firstWhere('id', $m->item_id);
            $targetQtyInBase = convertUnit($m->quantity, $m->unit_code, $item->base_unit_code);

            $currentQtyInBase = (string) InventoryStock::where('item_id', $m->item_id)
                ->where('location_id', $m->location_id)
                ->sum('remaining');

            $delta = bcsub($targetQtyInBase, $currentQtyInBase, 10);
            $cmp = bccomp($delta, '0', 10);

            if ($cmp > 0) {
                $stock = InventoryStock::create([
                    'item_id'       => $m->item_id,
                    'location_id'   => $m->location_id,
                    'unit_cost'     => 0, // no monatary value - no expense
                    'currency_code' => $m->currency_code,
                    'quantity'      => (int) $delta,
                    'remaining'     => (int) $delta,
                    'unit_code'     => $item->base_unit_code,
                    'metadata'      => $m->metadata,
                ]);

                InventoryMovement::create([
                    'movement_type'  => MovementType::In,
                    'transaction_id' => $tx->id,
                    'item_id'        => $m->item_id,
                    'location_id'    => $m->location_id,
                    'stock_id'       => $stock->id,
                    'quantity'       => (int) $delta,
                    'unit_code'      => $item->base_unit_code,
                    'unit_cost'      => 0,
                    'currency_code'  => $m->currency_code,
                    'metadata'       => $m->metadata,
                ]);
            } elseif ($cmp < 0) {
                $this->applyDeduction($tx, $m, $item, bcsub('0', $delta, 10));
            }
        }
    }

    protected function validateMovementDirections(TransactionDataDTO $transaction): void
    {
        match ($transaction->transaction_type) {
            TransactionType::In => $transaction->movements->each(function ($m): void {
                if ($m->type !== MovementType::In) {
                    throw new \InvalidArgumentException("All movements for an 'IN' transaction must be of type 'in'.");
                }
            }),
            TransactionType::Out => $transaction->movements->each(function ($m): void {
                if ($m->type !== MovementType::Out) {
                    throw new \InvalidArgumentException("All movements for an 'OUT' transaction must be of type 'out'.");
                }
            }),
            default => null,
        };
    }

    /**
     * Ensure that each movement unit_code belongs to the same group with $item->base_unit_code
     */
    protected function validateUnitGroups(TransactionDataDTO $transaction, Collection $items): void
    {
        foreach ($transaction->movements as $movement) {
            /** @var InventoryItem $item */
            $item = $items->firstWhere('id', $movement->item_id);

            if (!$item->base_unit_code) {
                throw new \RuntimeException("Item {$item->id} has no base_unit_code defined.");
            }

            $baseUnit = $this->unitCache->getByCode($item->base_unit_code);
            $providedUnit = $this->unitCache->getByCode($movement->unit_code);

            if (bccomp((string) $baseUnit->ratio, '1', 10) !== 0) {
                throw new \RuntimeException("Item {$item->id} base_unit_code does not have ratio 1.");
            }

            if ($baseUnit->group_id !== $providedUnit->group_id) {
                throw new UnitGroupMismatchException(
                    $item->id,
                    $movement->unit_code,
                    $item->base_unit_code
                );
            }
        }
    }

    protected function validateTransferBalance(TransactionDataDTO $transaction, Collection $items): void
    {
        // For TRANSFER, for each item: SUM(IN) == SUM(OUT) in base units
        $itemMovements = $transaction->movements->groupBy('item_id');

        foreach ($itemMovements as $itemId => $movements) {
            $item = $items->firstWhere('id', $itemId);

            $totalIn = '0';
            $totalOut = '0';

            foreach ($movements as $movement) {
                $amountInBase = convertUnit($movement->quantity, $movement->unit_code, $item->base_unit_code);

                if ($movement->type === MovementType::In) {
                    $totalIn = bcadd($totalIn, $amountInBase, 10);
                } else {
                    $totalOut = bcadd($totalOut, $amountInBase, 10);
                }
            }

            if (bccomp($totalIn, $totalOut, 10) !== 0) {
                throw new TransferBalanceException(
                    $itemId,
                    $totalIn,
                    $totalOut,
                    $item->base_unit_code
                );
            }
        }
    }
}
