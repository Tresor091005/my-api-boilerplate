<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\DTO\MovementDataDTO;
use Lahatre\Inventory\DTO\TransactionDataDTO;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\AdjustmentNoOpException;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Exceptions\TransferDistributionException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Support\InventoryReferenceResolver;
use Lahatre\Inventory\Validation\TransactionValidator;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Support\UnitCache;

class InventoryService implements InventoryInterface
{
    /**
     * @var array<string, Collection<int, InventoryStock>>
     */
    protected array $stockSelectionCache = [];

    public function __construct(
        protected UnitCache $unitCache,
        protected MasterInterface $masterInterface,
        protected TransactionValidator $transactionValidator,
        protected InventoryReferenceResolver $inventoryReferenceResolver,
    ) {}

    public function createLocation(HasInventoryLocation $model): InventoryLocation
    {
        return DB::transaction(
            fn (): InventoryLocation => $this->inventoryReferenceResolver->ensureInventoryLocations(collect([$model]))->firstOrFail()
        );
    }

    /**
     * @param  array<int, HasInventoryLocation>|Collection<int, HasInventoryLocation>  $models
     */
    public function createManyLocations(array|Collection $models): Collection
    {
        return DB::transaction(
            fn (): Collection => $this->inventoryReferenceResolver->ensureInventoryLocations(collect($models))
        );
    }

    public function createItem(HasInventoryItem $model): InventoryItem
    {
        return DB::transaction(
            fn (): InventoryItem => $this->inventoryReferenceResolver->ensureInventoryItems(collect([$model]))->firstOrFail()
        );
    }

    /**
     * @param  array<int, HasInventoryItem>|Collection<int, HasInventoryItem>  $models
     */
    public function createManyItems(array|Collection $models): Collection
    {
        return DB::transaction(
            fn (): Collection => $this->inventoryReferenceResolver->ensureInventoryItems(collect($models))
        );
    }

    public function updateLocation(HasInventoryLocation $model, array $data): InventoryLocation
    {
        $validated = validator($data, [
            'is_active' => ['boolean'],
        ])->validate();

        return DB::transaction(function () use ($model, $validated) {
            $location = $this->resolveLocation($model);
            $location->fill([
                'is_active' => $validated['is_active'] ?? $location->is_active,
            ]);
            $location->save();

            return $location;
        });
    }

    public function updateItem(HasInventoryItem $model, array $data): InventoryItem
    {
        $validated = validator($data, [
            'sku'                => ['string', 'max:255'],
            'is_active'          => ['boolean'],
            'deduction_strategy' => ['nullable', Rule::enum(DeductionStrategy::class)],
        ])->validate();

        return DB::transaction(function () use ($model, $validated) {
            $item = $this->resolveItem($model);
            $item->fill([
                'sku'                => $validated['sku'] ?? $item->sku,
                'is_active'          => $validated['is_active'] ?? $item->is_active,
                'deduction_strategy' => array_key_exists('deduction_strategy', $validated) ? $validated['deduction_strategy'] : $item->deduction_strategy,
            ]);
            $item->save();

            return $item;
        });
    }

    public function deleteLocation(HasInventoryLocation $model): void
    {
        DB::transaction(function () use ($model): void {
            $this->resolveLocation($model)->delete();
        });
    }

    public function deleteItem(HasInventoryItem $model): void
    {
        DB::transaction(function () use ($model): void {
            $this->resolveItem($model)->delete();
        });
    }

    public function recordTransaction(array $data): InventoryTransaction
    {
        return DB::transaction(function () use ($data) {
            $this->stockSelectionCache = [];
            $resolvedData = $this->resolveTransactionReferences($data);
            [$validatedData, $lookups] = $this->transactionValidator->validate($resolvedData);
            $transaction = TransactionDataDTO::fromArray($validatedData, $this->masterInterface);

            /** @var Collection<string, InventoryItem> $items */
            $items = $lookups['items'];
            $movementContexts = $this->buildMovementContexts($transaction, $items);

            $tx = InventoryTransaction::create([
                'reference_type'   => $transaction->reference_type,
                'reference_id'     => $transaction->reference_id,
                'transaction_type' => $transaction->transaction_type,
                'metadata'         => $transaction->metadata,
            ]);

            match ($transaction->transaction_type) {
                TransactionType::In         => $this->processInMovements($tx, $movementContexts),
                TransactionType::Out        => $this->processOutMovements($tx, $movementContexts),
                TransactionType::Transfer   => $this->processTransferMovements($tx, $movementContexts),
                TransactionType::Adjustment => $this->processAdjustmentMovements($tx, $movementContexts),
            };

            return $tx->load('movements');
        });
    }

    protected function resolveTransactionReferences(array $data): array
    {
        if (!(bool) config('inventory.enable_model_reference_preprocessing', false)) {
            return $data;
        }

        return $this->inventoryReferenceResolver->preprocessTransactionData($data);
    }

    protected function resolveItem(HasInventoryItem $model): InventoryItem
    {
        return InventoryItem::query()
            ->where('itemable_type', $model->getMorphClass())
            ->where('itemable_id', (string) $model->getKey())
            ->firstOrFail();
    }

    protected function resolveLocation(HasInventoryLocation $model): InventoryLocation
    {
        return InventoryLocation::query()
            ->where('external_type', $model->getMorphClass())
            ->where('external_id', (string) $model->getKey())
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, array{movement: MovementDataDTO, item: InventoryItem, quantity_in_base: string}>  $movementContexts
     */
    protected function processInMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        foreach ($movementContexts as $context) {
            $this->createInboundStockAndMovement(
                tx: $tx,
                movement: $context['movement'],
                item: $context['item'],
                quantityInBase: $context['quantity_in_base'],
            );
        }
    }

    /**
     * @param  Collection<int, array{movement: MovementDataDTO, item: InventoryItem, quantity_in_base: string}>  $movementContexts
     */
    protected function processOutMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        foreach ($movementContexts as $context) {
            $this->applyDeduction(
                tx: $tx,
                movement: $context['movement'],
                item: $context['item'],
                quantityToDeduct: $context['quantity_in_base'],
            );
        }
    }

    protected function applyDeduction(
        InventoryTransaction $tx,
        MovementDataDTO $movement,
        InventoryItem $item,
        string $quantityToDeduct,
        ?Collection $stocks = null
    ): Collection {
        $stocks ??= $this->resolveStocksForDeduction($movement, $item);

        $totalAvailable = (string) $stocks->sum('remaining');

        if (bccomp($quantityToDeduct, $totalAvailable, 10) > 0) {
            throw new InsufficientStockException(
                $movement->item_id,
                $movement->location_id,
                $movement->quantity,
                $this->masterInterface->convertUnit($totalAvailable, $item->base_unit_code, $movement->unit_code),
                $movement->unit_code
            );
        }

        $remainingToDeduct = $quantityToDeduct;
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

            $outMovement = InventoryMovement::create([
                'movement_type'   => MovementType::Out,
                'transaction_id'  => $tx->id,
                'item_id'         => $movement->item_id,
                'location_id'     => $movement->location_id,
                'stock_id'        => $stock->id,
                'quantity'        => (int) $deduction,
                'unit_code'       => $item->base_unit_code,
                'unit_cost'       => $stock->unit_cost,
                'currency_code'   => $stock->currency_code,
                'expiration_date' => $stock->expiration_date,
                'metadata'        => $movement->metadata,
            ]);

            $outMovement->setRelation('stock', $stock);
            $movements->push($outMovement);

            $remainingToDeduct = bcsub($remainingToDeduct, $deduction, 10);
        }

        return $movements;
    }

    /**
     * @param  Collection<int, array{movement: MovementDataDTO, item: InventoryItem, quantity_in_base: string}>  $movementContexts
     */
    protected function processTransferMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        $groupedByItem = $movementContexts->groupBy(fn (array $context): string => $context['movement']->item_id);

        foreach ($groupedByItem as $itemId => $itemMovementContexts) {
            $item = $itemMovementContexts->first()['item'];
            $outMovementContexts = $itemMovementContexts
                ->filter(fn (array $context): bool => $context['movement']->type === MovementType::Out)
                ->values();
            $inMovementContexts = $itemMovementContexts
                ->filter(fn (array $context): bool => $context['movement']->type === MovementType::In)
                ->values();

            $batches = $this->collectDeductedTransferBatches($tx, $outMovementContexts);

            $this->distributeTransferBatches(
                tx: $tx,
                itemId: $itemId,
                item: $item,
                inMovementContexts: $inMovementContexts,
                batches: $batches,
            );
        }
    }

    /**
     * @param  Collection<int, array{movement: MovementDataDTO, item: InventoryItem, quantity_in_base: string}>  $movementContexts
     */
    protected function processAdjustmentMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        foreach ($movementContexts as $context) {
            $movement = $context['movement'];
            $item = $context['item'];
            $targetQtyInBase = $context['quantity_in_base'];
            $stocks = $this->resolveStocksForDeduction($movement, $item);

            $currentQtyInBase = (string) $stocks->sum('remaining');
            $delta = bcsub($targetQtyInBase, $currentQtyInBase, 10);
            $cmp = bccomp($delta, '0', 10);

            if ($cmp > 0) {
                $this->createInboundStockAndMovement(
                    tx: $tx,
                    movement: $movement,
                    item: $item,
                    quantityInBase: $delta,
                    unitCost: $movement->unit_cost ?? 0,
                    currencyCode: $movement->currency_code,
                );
            } elseif ($cmp < 0) {
                $this->applyDeduction($tx, $movement, $item, bcsub('0', $delta, 10), $stocks);
            } else {
                throw new AdjustmentNoOpException($movement->item_id, $movement->location_id);
            }
        }
    }

    /**
     * @param  Collection<string, InventoryItem>  $items
     * @return Collection<int, array{movement: MovementDataDTO, item: InventoryItem, quantity_in_base: string}>
     */
    protected function buildMovementContexts(TransactionDataDTO $transaction, Collection $items): Collection
    {
        return $transaction->movements->map(function (MovementDataDTO $movement) use ($items): array {
            $item = $items->get($movement->item_id);

            return [
                'movement'         => $movement,
                'item'             => $item,
                'quantity_in_base' => $this->masterInterface->convertUnit(
                    $movement->quantity,
                    $movement->unit_code,
                    $item->base_unit_code
                ),
            ];
        });
    }

    protected function createInboundStockAndMovement(
        InventoryTransaction $tx,
        MovementDataDTO $movement,
        InventoryItem $item,
        string $quantityInBase,
        ?int $unitCost = null,
        ?string $currencyCode = null,
        ?array $stockMetadata = null
    ): InventoryStock {
        $stock = InventoryStock::create([
            'item_id'         => $movement->item_id,
            'location_id'     => $movement->location_id,
            'unit_cost'       => $unitCost ?? $movement->unit_cost,
            'currency_code'   => $currencyCode ?? $movement->currency_code,
            'quantity'        => (int) $quantityInBase,
            'remaining'       => (int) $quantityInBase,
            'unit_code'       => $item->base_unit_code,
            'expiration_date' => $movement->expiration_date,
            'metadata'        => $stockMetadata ?? $movement->metadata,
        ]);

        InventoryMovement::create([
            'movement_type'   => MovementType::In,
            'transaction_id'  => $tx->id,
            'item_id'         => $movement->item_id,
            'location_id'     => $movement->location_id,
            'stock_id'        => $stock->id,
            'quantity'        => (int) $quantityInBase,
            'unit_code'       => $item->base_unit_code,
            'unit_cost'       => $unitCost ?? $movement->unit_cost,
            'currency_code'   => $currencyCode ?? $movement->currency_code,
            'expiration_date' => $movement->expiration_date,
            'metadata'        => $movement->metadata,
        ]);

        return $stock;
    }

    /**
     * @param  Collection<int, array{movement: MovementDataDTO, item: InventoryItem, quantity_in_base: string}>  $outMovementContexts
     * @return list<InventoryMovement>
     */
    protected function collectDeductedTransferBatches(InventoryTransaction $tx, Collection $outMovementContexts): array
    {
        $batches = [];

        foreach ($outMovementContexts as $context) {
            $deductedMovements = $this->applyDeduction(
                tx: $tx,
                movement: $context['movement'],
                item: $context['item'],
                quantityToDeduct: $context['quantity_in_base'],
            );

            foreach ($deductedMovements as $deductedMovement) {
                $batches[] = $deductedMovement;
            }
        }

        return $batches;
    }

    /**
     * @param  Collection<int, array{movement: MovementDataDTO, item: InventoryItem, quantity_in_base: string}>  $inMovementContexts
     * @param  list<InventoryMovement>  $batches
     */
    protected function distributeTransferBatches(
        InventoryTransaction $tx,
        string $itemId,
        InventoryItem $item,
        Collection $inMovementContexts,
        array $batches
    ): void {
        $batchIndex = 0;

        foreach ($inMovementContexts as $context) {
            $movement = $context['movement'];
            $remainingToFill = $context['quantity_in_base'];

            while (bccomp($remainingToFill, '0', 10) > 0 && isset($batches[$batchIndex])) {
                $currentBatch = $batches[$batchIndex];
                $batchAvailable = (string) $currentBatch->quantity;

                $take = bccomp($remainingToFill, $batchAvailable, 10) >= 0
                    ? $batchAvailable
                    : $remainingToFill;

                $this->createInboundStockAndMovement(
                    tx: $tx,
                    movement: $movement,
                    item: $item,
                    quantityInBase: $take,
                    unitCost: $currentBatch->unit_cost,
                    currencyCode: $currentBatch->currency_code,
                    stockMetadata: array_merge($currentBatch->stock->metadata ?? [], $movement->metadata ?? []),
                );

                $remainingToFill = bcsub($remainingToFill, $take, 10);

                if (bccomp($take, $batchAvailable, 10) === 0) {
                    $batchIndex++;
                } else {
                    $currentBatch->quantity = (int) bcsub($batchAvailable, $take, 0);
                }
            }

            if (bccomp($remainingToFill, '0', 10) > 0) {
                throw new TransferDistributionException(
                    "Transfer imbalance detected for item {$itemId}: Destination location {$movement->location_id} could not be fully filled from source stocks."
                );
            }
        }

        if (isset($batches[$batchIndex])) {
            throw new TransferDistributionException(
                "Transfer imbalance detected for item {$itemId}: Source stocks were not fully distributed to destinations."
            );
        }
    }

    protected function resolveStocksForDeduction(MovementDataDTO $movement, InventoryItem $item): Collection
    {
        $strategy = $this->resolveDeductionStrategy($movement, $item);
        $cacheKey = $this->buildStockSelectionCacheKey($movement, $strategy);

        if (isset($this->stockSelectionCache[$cacheKey])) {
            return $this->stockSelectionCache[$cacheKey];
        }

        $query = InventoryStock::where('item_id', $movement->item_id)
            ->where('location_id', $movement->location_id)
            ->where('remaining', '>', 0)
            ->lockForUpdate();

        $stocks = match ($strategy) {
            DeductionStrategy::Fifo => $query->orderBy('created_at', 'asc')->get(),
            DeductionStrategy::Fefo => $query->orderByRaw('expiration_date ASC NULLS LAST')
                ->orderBy('created_at', 'asc')->get(),
            DeductionStrategy::Manual => $this->orderStocksManually(
                $query->whereIn('id', $movement->stock_ids)->get(),
                $movement->stock_ids ?? [],
            ),
        };

        return $this->stockSelectionCache[$cacheKey] = $stocks;
    }

    protected function resolveDeductionStrategy(MovementDataDTO $movement, InventoryItem $item): DeductionStrategy
    {
        return $movement->strategy
            ?? $item->deduction_strategy
            ?? DeductionStrategy::tryFrom((string) config('inventory.default_strategy'))
            ?? DeductionStrategy::Fifo;
    }

    protected function buildStockSelectionCacheKey(MovementDataDTO $movement, DeductionStrategy $strategy): string
    {
        return implode('|', [
            $movement->item_id,
            $movement->location_id,
            $strategy->value,
            implode(',', $movement->stock_ids ?? []),
        ]);
    }

    /**
     * @param  Collection<int, InventoryStock>  $stocks
     * @param  array<int, string>  $stockIds
     * @return Collection<int, InventoryStock>
     */
    protected function orderStocksManually(Collection $stocks, array $stockIds): Collection
    {
        $indexedStocks = $stocks->keyBy('id');

        return collect($stockIds)
            ->map(fn (string $stockId): ?InventoryStock => $indexedStocks->get($stockId))
            ->filter()
            ->values();
    }
}
