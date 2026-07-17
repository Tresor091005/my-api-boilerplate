<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\DTO\MovementDataDTO;
use Lahatre\Inventory\DTO\MovementExecutionContextDTO;
use Lahatre\Inventory\DTO\TransactionDataDTO;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\AdjustmentNoOpException;
use Lahatre\Inventory\Exceptions\IdempotencyKeyReuseException;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Exceptions\TransferDistributionException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\Item\ManageInventoryItemService;
use Lahatre\Inventory\Services\Location\ManageInventoryLocationService;
use Lahatre\Inventory\Traits\ResolvesInventoryOrganization;
use Lahatre\Inventory\Validation\TransactionValidator;
use Lahatre\Master\Contracts\MasterInterface;

class InventoryService implements InventoryInterface
{
    use ResolvesInventoryOrganization;

    /**
     * @var array<string, Collection<int, InventoryStock>>
     */
    protected array $stockSelectionCache = [];

    public function __construct(
        protected MasterInterface $masterInterface,
        protected TransactionValidator $transactionValidator,
        protected ManageInventoryItemService $inventoryItemService,
        protected ManageInventoryLocationService $inventoryLocationService,
        protected TransactionPayloadHasher $transactionPayloadHasher,
    ) {}

    public function createLocation(HasInventoryLocation $model): InventoryLocation
    {
        return $this->inventoryLocationService->create($model);
    }

    /**
     * @param  array<int, HasInventoryLocation>|Collection<int, HasInventoryLocation>  $models
     */
    public function createManyLocations(array|Collection $models): Collection
    {
        return $this->inventoryLocationService->createMany($models);
    }

    public function createItem(HasInventoryItem $model): InventoryItem
    {
        return $this->inventoryItemService->create($model);
    }

    /**
     * @param  array<int, HasInventoryItem>|Collection<int, HasInventoryItem>  $models
     */
    public function createManyItems(array|Collection $models): Collection
    {
        return $this->inventoryItemService->createMany($models);
    }

    public function updateLocation(HasInventoryLocation $model, array $data): InventoryLocation
    {
        return $this->inventoryLocationService->update($model, $data);
    }

    public function updateItem(HasInventoryItem $model, array $data): InventoryItem
    {
        return $this->inventoryItemService->update($model, $data);
    }

    public function deleteLocation(HasInventoryLocation $model): void
    {
        $this->inventoryLocationService->delete($model);
    }

    public function deleteItem(HasInventoryItem $model): void
    {
        $this->inventoryItemService->delete($model);
    }

    /**
     * @param  array<int|string, mixed>  $with
     */
    public function recordTransaction(array $data, array $with = ['movements']): InventoryTransaction
    {
        $organizationId = $this->organizationId();

        return DB::transaction(function () use ($data, $with, $organizationId): InventoryTransaction {
            $this->stockSelectionCache = [];
            $resolvedData = $this->resolveTransactionReferences($data);
            [$validatedData, $lookups] = $this->transactionValidator->validate($resolvedData);
            $transaction = TransactionDataDTO::fromArray($validatedData, $this->masterInterface);
            $payloadHash = $this->transactionPayloadHasher->hash($transaction);

            /** @var Collection<string, InventoryItem> $items */
            $items = $lookups['items'];
            $movementContexts = $this->buildMovementContexts($transaction, $items);

            $tx = InventoryTransaction::query()->firstOrCreate(
                [
                    'organization_id' => $organizationId,
                    'idempotency_key' => $transaction->idempotency_key,
                ],
                [
                    'payload_hash'     => $payloadHash,
                    'reference_type'   => $transaction->reference_type,
                    'reference_id'     => $transaction->reference_id,
                    'transaction_type' => $transaction->transaction_type,
                    'metadata'         => $transaction->metadata,
                ]
            );

            if (!$tx->wasRecentlyCreated) {
                if ($tx->payload_hash !== $payloadHash) {
                    throw new IdempotencyKeyReuseException(
                        $transaction->idempotency_key,
                        $tx->payload_hash,
                        $payloadHash
                    );
                }

                return $tx->load($with);
            }

            match ($transaction->transaction_type) {
                TransactionType::In         => $this->processInMovements($tx, $movementContexts),
                TransactionType::Out        => $this->processOutMovements($tx, $movementContexts),
                TransactionType::Transfer   => $this->processTransferMovements($tx, $movementContexts),
                TransactionType::Adjustment => $this->processAdjustmentMovements($tx, $movementContexts),
            };

            return $tx->load($with);
        });
    }

    /**
     * Normalizes optional external item and location model references into internal inventory IDs
     * before transaction validation and ledger persistence.
     *
     * When enabled, missing inventory records are ensured through the dedicated management services.
     */
    protected function resolveTransactionReferences(array $data): array
    {
        if (!(bool) config('inventory.enable_model_reference_preprocessing', false)) {
            return $data;
        }

        $movements = collect($data['movements'] ?? []);

        $resolvedItems = $this->inventoryItemService->ensure(
            $movements
                ->map(fn (mixed $movement): mixed => is_array($movement) ? ($movement['item'] ?? $movement['item_id'] ?? null) : null)
                ->filter(fn (mixed $reference): bool => $reference instanceof HasInventoryItem)
                ->values()
        )->keyBy(fn (InventoryItem $item): string => $item->itemable_type.':'.$item->itemable_id);

        $resolvedLocations = $this->inventoryLocationService->ensure(
            $movements
                ->map(fn (mixed $movement): mixed => is_array($movement) ? ($movement['location'] ?? $movement['location_id'] ?? null) : null)
                ->filter(fn (mixed $reference): bool => $reference instanceof HasInventoryLocation)
                ->values()
        )->keyBy(fn (InventoryLocation $location): string => $location->external_type.':'.$location->external_id);

        $data['movements'] = $movements->map(function (mixed $movement) use ($resolvedItems, $resolvedLocations): mixed {
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

        return $data;
    }

    /**
     * @param  Collection<string, InventoryItem>  $items
     * @return Collection<int, MovementExecutionContextDTO>
     */
    protected function buildMovementContexts(TransactionDataDTO $transaction, Collection $items): Collection
    {
        return $transaction->movements->map(function (MovementDataDTO $movement) use ($items): MovementExecutionContextDTO {
            $item = $items->get($movement->item_id);

            return new MovementExecutionContextDTO(
                movement: $movement,
                item: $item,
                quantityInBase: $this->masterInterface->convertUnit(
                    $movement->quantity,
                    $movement->unit_code,
                    $item->base_unit_code
                ),
            );
        });
    }

    /**
     * @param  Collection<int, MovementExecutionContextDTO>  $movementContexts
     */
    protected function processInMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        foreach ($movementContexts as $context) {
            $this->applyInbound(
                tx: $tx,
                context: $context,
            );
        }
    }

    /**
     * @param  Collection<int, MovementExecutionContextDTO>  $movementContexts
     */
    protected function processOutMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        foreach ($movementContexts as $context) {
            $this->applyDeduction(
                tx: $tx,
                context: $context,
                quantityToDeduct: $context->quantityInBase,
            );
        }
    }

    /**
     * @param  Collection<int, MovementExecutionContextDTO>  $movementContexts
     */
    protected function processTransferMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        $groupedByItem = $movementContexts->groupBy(fn (MovementExecutionContextDTO $context): string => $context->movement->item_id);

        foreach ($groupedByItem as $itemId => $itemMovementContexts) {
            $item = $itemMovementContexts->first()->item;
            $outMovementContexts = $itemMovementContexts
                ->filter(fn (MovementExecutionContextDTO $context): bool => $context->movement->type === MovementType::Out)
                ->values();
            $inMovementContexts = $itemMovementContexts
                ->filter(fn (MovementExecutionContextDTO $context): bool => $context->movement->type === MovementType::In)
                ->values();

            $this->applyTransferForItem(
                tx: $tx,
                item: $item,
                outMovementContexts: $outMovementContexts,
                inMovementContexts: $inMovementContexts,
            );
        }
    }

    /**
     * @param  Collection<int, MovementExecutionContextDTO>  $movementContexts
     */
    protected function processAdjustmentMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        foreach ($movementContexts as $context) {
            $movement = $context->movement;
            $item = $context->item;
            $targetQtyInBase = $context->quantityInBase;
            $stocks = $this->resolveStocksForDeduction($movement, $item);

            $currentQtyInBase = (string) $stocks->sum('remaining');
            $delta = bcsub($targetQtyInBase, $currentQtyInBase, 10);
            $cmp = bccomp($delta, '0', 10);

            if ($cmp > 0) {
                $this->applyInbound(
                    tx: $tx,
                    context: $context,
                    quantityOverrideInBase: $delta,
                    unitCost: $movement->unit_cost ?? 0,
                    currencyCode: $movement->currency_code,
                );
            } elseif ($cmp < 0) {
                $this->applyDeduction(
                    tx: $tx,
                    context: $context,
                    quantityToDeduct: bcsub('0', $delta, 10),
                    stocks: $stocks,
                );
            } else {
                throw new AdjustmentNoOpException($movement->item_id, $movement->location_id);
            }
        }
    }

    protected function applyInbound(
        InventoryTransaction $tx,
        MovementExecutionContextDTO $context,
        ?string $quantityOverrideInBase = null,
        ?int $unitCost = null,
        ?string $currencyCode = null,
        ?array $stockMetadata = null
    ): InventoryStock {
        $movement = $context->movement;
        $item = $context->item;
        $quantityInBase = $quantityOverrideInBase ?? $context->quantityInBase;

        $stock = InventoryStock::create([
            'organization_id' => $this->organizationId(),
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
            'organization_id' => $this->organizationId(),
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

    protected function applyDeduction(
        InventoryTransaction $tx,
        MovementExecutionContextDTO $context,
        string $quantityToDeduct,
        ?Collection $stocks = null
    ): Collection {
        $movement = $context->movement;
        $item = $context->item;

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
                'organization_id' => $this->organizationId(),
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
     * @param  Collection<int, MovementExecutionContextDTO>  $outMovementContexts
     * @param  Collection<int, MovementExecutionContextDTO>  $inMovementContexts
     */
    protected function applyTransferForItem(
        InventoryTransaction $tx,
        InventoryItem $item,
        Collection $outMovementContexts,
        Collection $inMovementContexts
    ): void {
        /** @var list<InventoryMovement> $batches */
        $batches = [];

        foreach ($outMovementContexts as $context) {
            $deductedMovements = $this->applyDeduction(
                tx: $tx,
                context: $context,
                quantityToDeduct: $context->quantityInBase,
            );

            foreach ($deductedMovements as $deductedMovement) {
                $batches[] = $deductedMovement;
            }
        }

        $batchIndex = 0;

        foreach ($inMovementContexts as $context) {
            $movement = $context->movement;
            $remainingToFill = $context->quantityInBase;

            while (bccomp($remainingToFill, '0', 10) > 0 && isset($batches[$batchIndex])) {
                $currentBatch = $batches[$batchIndex];
                $batchAvailable = (string) $currentBatch->quantity;

                $take = bccomp($remainingToFill, $batchAvailable, 10) >= 0
                    ? $batchAvailable
                    : $remainingToFill;

                $this->applyInbound(
                    tx: $tx,
                    context: $context,
                    quantityOverrideInBase: $take,
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
                throw TransferDistributionException::destinationImbalance($item->id, $movement->location_id);
            }
        }

        if (isset($batches[$batchIndex])) {
            throw TransferDistributionException::sourceImbalance($item->id);
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
            ->where('organization_id', $this->organizationId())
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
            ->unique()
            ->map(fn (string $stockId): ?InventoryStock => $indexedStocks->get($stockId))
            ->filter()
            ->values();
    }
}
