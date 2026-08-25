<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Contracts\HasInventoryLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Data\InventoryItemConfigurationData;
use Lahatre\Inventory\Data\MovementData;
use Lahatre\Inventory\Data\MovementExecutionContextData;
use Lahatre\Inventory\Data\TransactionData;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Exceptions\AdjustmentAverageCostUnavailableException;
use Lahatre\Inventory\Exceptions\AdjustmentNoOpException;
use Lahatre\Inventory\Exceptions\IdempotencyKeyReuseException;
use Lahatre\Inventory\Exceptions\InboundCostRequiredException;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Inventory\Exceptions\PreviewRollbackException;
use Lahatre\Inventory\Exceptions\ReversalException;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Inventory\Services\Item\ManageInventoryItemService;
use Lahatre\Inventory\Services\Location\ManageInventoryLocationService;
use Lahatre\Inventory\Validation\TransactionValidator;
use Lahatre\Master\Contracts\MasterInterface;

class InventoryService implements InventoryInterface
{
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
        protected TransactionErrorKeyMapper $transactionErrorKeyMapper,
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

    public function createItem(HasInventoryItem $model, ?InventoryItemConfigurationData $configuration = null): InventoryItem
    {
        return $this->inventoryItemService->create($model, $configuration);
    }

    /**
     * @param  array<int, HasInventoryItem>|Collection<int, HasInventoryItem>  $models
     */
    public function createManyItems(array|Collection $models, array|Collection $configurations = []): Collection
    {
        return $this->inventoryItemService->createMany($models, $configurations);
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
    public function recordTransaction(array $data, array $with = ['movements'], ?array $errorKeyMap = null): InventoryTransaction
    {
        $this->transactionErrorKeyMapper->validate($errorKeyMap);

        try {
            return DB::transaction(fn (): InventoryTransaction => $this->recordTransactionInternal($data, $with));
        } catch (ValidationException $exception) {
            throw $this->transactionErrorKeyMapper->mapValidationException($exception, $errorKeyMap);
        }
    }

    public function previewTransaction(array $data, ?array $errorKeyMap = null): void
    {
        $this->transactionErrorKeyMapper->validate($errorKeyMap);

        try {
            $this->runPreview(function () use ($data): void {
                $this->recordTransactionInternal(
                    data: $data,
                    with: [],
                    preview: true,
                );
            });
        } catch (ValidationException $exception) {
            throw $this->transactionErrorKeyMapper->mapValidationException($exception, $errorKeyMap);
        }
    }

    public function reverseTransaction(string $originalTransactionId, ?array $metadata = null, array $with = ['movements'], ?array $errorKeyMap = null): InventoryTransaction
    {
        $this->transactionErrorKeyMapper->validate($errorKeyMap);
        try {
            return DB::transaction(function () use ($originalTransactionId, $metadata, $with): InventoryTransaction {
                $original = $this->loadTransactionForReversal(
                    originalTransactionId: $originalTransactionId,
                    organizationId: currentOrganizationId(),
                );

                $payload = $this->buildReversalPayload($original, $metadata);

                if ($original->reversal !== null) {
                    if ($original->reversal->idempotency_key !== $payload['idempotency_key']) {
                        throw ReversalException::alreadyReversed($original->id);
                    }

                    return $this->recordTransactionInternal($payload, $with, null, true, false, true);
                }

                $this->assertReversalItemsTrackStock($original);

                return $this->recordTransactionInternal($payload, $with, $original->id, true, false, true);
            });
        } catch (ValidationException $exception) {
            throw $this->transactionErrorKeyMapper->mapValidationException($exception, $errorKeyMap);
        }
    }

    public function previewReversal(string $originalTransactionId, ?array $metadata = null, ?array $errorKeyMap = null): void
    {
        $this->transactionErrorKeyMapper->validate($errorKeyMap);
        try {
            $this->runPreview(function () use ($originalTransactionId, $metadata): void {
                $original = $this->loadTransactionForReversal(
                    originalTransactionId: $originalTransactionId,
                    organizationId: currentOrganizationId(),
                );

                if ($original->reversal !== null) {
                    throw ReversalException::alreadyReversed($original->id);
                }

                $this->assertReversalItemsTrackStock($original);

                $this->recordTransactionInternal(
                    data: $this->buildReversalPayload($original, $metadata),
                    with: [],
                    reversalOfTransactionId: $original->id,
                    costsInMinor: true,
                    preview: true,
                    allowLegacyExpiration: true,
                );
            });
        } catch (ValidationException $exception) {
            throw $this->transactionErrorKeyMapper->mapValidationException($exception, $errorKeyMap);
        }
    }

    /**
     * @param  Closure(): void  $operation
     */
    protected function runPreview(Closure $operation): void
    {
        try {
            DB::transaction(function () use ($operation): void {
                Model::withoutEvents(function () use ($operation): void {
                    $operation();

                    throw new PreviewRollbackException;
                });
            });
        } catch (PreviewRollbackException) {
            return;
        }
    }

    protected function loadTransactionForReversal(
        string $originalTransactionId,
        string $organizationId,
    ): InventoryTransaction {
        $original = InventoryTransaction::query()
            ->where('organization_id', $organizationId)
            ->whereKey($originalTransactionId)
            ->with([
                'movements.stock' => fn ($query) => $query->withTrashed(),
                'reversal',
            ])
            ->lockForUpdate()
            ->first();

        if (!$original) {
            throw ReversalException::transactionNotFound($originalTransactionId);
        }

        if ($original->reversal_of_transaction_id !== null) {
            throw ReversalException::cannotReverseReversal($original->id);
        }

        return $original;
    }

    protected function assertReversalItemsTrackStock(InventoryTransaction $transaction): void
    {
        $itemIds = $transaction->movements->pluck('item_id')->unique()->values();

        $disabledItemId = InventoryItem::query()
            ->where('organization_id', currentOrganizationId())
            ->whereIn('id', $itemIds)
            ->where('stock_tracking_enabled', false)
            ->value('id');

        if (is_string($disabledItemId)) {
            throw ReversalException::itemTrackingDisabled($disabledItemId);
        }
    }

    protected function recordTransactionInternal(
        array $data,
        array $with = ['movements'],
        ?string $reversalOfTransactionId = null,
        bool $costsInMinor = false,
        bool $preview = false,
        bool $allowLegacyExpiration = false,
    ): InventoryTransaction {
        $organizationId = currentOrganizationId();

        $this->stockSelectionCache = [];
        $transferReversalBatches = $data['_transfer_reversal_batches'] ?? null;
        $resolvedData = $this->resolveTransactionReferences($data);
        [$validatedData, $lookups] = $this->transactionValidator->validate($resolvedData, $allowLegacyExpiration);
        if ($preview) {
            $validatedData['idempotency_key'] = 'preview-'.Str::uuid()->toString();
        }

        /** @var Collection<string, InventoryItem> $items */
        $items = $lookups['items'];
        $transaction = TransactionData::fromArray($validatedData, $this->masterInterface, $costsInMinor);
        $payloadHash = $this->transactionPayloadHasher->hash($transaction);
        $movementContexts = $this->buildMovementContexts($transaction, $items);

        $tx = InventoryTransaction::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'idempotency_key' => $transaction->idempotencyKey,
            ],
            [
                'payload_hash'               => $payloadHash,
                'reference_type'             => $transaction->referenceType,
                'reference_id'               => $transaction->referenceId,
                'transaction_type'           => $transaction->transactionType,
                'metadata'                   => $transaction->metadata,
                'reversal_of_transaction_id' => $reversalOfTransactionId,
            ]
        );

        if (!$tx->wasRecentlyCreated) {
            if ($tx->payload_hash !== $payloadHash) {
                throw new IdempotencyKeyReuseException(
                    $transaction->idempotencyKey,
                    $tx->payload_hash,
                    $payloadHash
                );
            }

            return $tx->load($with);
        }

        match ($transaction->transactionType) {
            TransactionType::In       => $this->processInMovements($tx, $movementContexts),
            TransactionType::Out      => $this->processOutMovements($tx, $movementContexts),
            TransactionType::Transfer => $transferReversalBatches === null
                ? $this->processTransferMovements($tx, $movementContexts)
                : $this->processTransferReversalMovements($tx, $transferReversalBatches, $movementContexts),
            TransactionType::Adjustment => $this->processAdjustmentMovements($tx, $movementContexts),
        };

        return $tx->load($with);
    }

    protected function buildReversalPayload(InventoryTransaction $original, ?array $metadata): array
    {
        if ($original->transaction_type === TransactionType::Transfer) {
            return $this->buildTransferReversalPayload($original, $metadata);
        }

        $transactionType = match ($original->transaction_type) {
            TransactionType::In  => TransactionType::Out,
            TransactionType::Out => TransactionType::In,
            default              => throw ReversalException::typeNotSupported($original->id, $original->transaction_type->value),
        };

        $movements = $original->movements->map(function (InventoryMovement $movement) use ($original): array {
            if ($original->transaction_type === TransactionType::In) {
                return [
                    'item_id'     => $movement->item_id,
                    'location_id' => $movement->location_id,
                    'type'        => MovementType::Out->value,
                    'quantity'    => $movement->quantity,
                    'unit_code'   => $movement->unit_code,
                    'strategy'    => DeductionStrategy::Manual->value,
                    'stock_ids'   => [$movement->stock_id],
                    'metadata'    => $movement->metadata,
                ];
            }

            if ($movement->currency_code === null) {
                throw ReversalException::inconsistentMovement($movement->id);
            }

            return [
                'item_id'         => $movement->item_id,
                'location_id'     => $movement->location_id,
                'type'            => MovementType::In->value,
                'quantity'        => $movement->quantity,
                'unit_code'       => $movement->unit_code,
                'total_cost'      => $movement->total_cost,
                'currency_code'   => $movement->currency_code,
                'expiration_date' => $movement->expiration_date,
                'metadata'        => $movement->metadata,
                'stock_metadata'  => $movement->stock_metadata_snapshot,
            ];
        })->values()->all();

        return [
            'idempotency_key'  => $original->id.':reverse',
            'reference_type'   => $original->reference_type,
            'reference_id'     => $original->reference_id,
            'transaction_type' => $transactionType->value,
            'metadata'         => $metadata,
            'movements'        => $movements,
        ];
    }

    protected function buildTransferReversalPayload(InventoryTransaction $original, ?array $metadata): array
    {
        $groups = $original->movements->groupBy('link_id');
        if ($groups->has('')) {
            throw ReversalException::inconsistentMovement($original->movements->first()->id);
        }

        $movements = [];
        $batches = [];

        foreach ($groups as $linkId => $group) {
            $outbound = $group->where('movement_type', MovementType::Out);
            $inbound = $group->where('movement_type', MovementType::In);

            if ($outbound->isEmpty() || $inbound->isEmpty()) {
                throw ReversalException::inconsistentMovement($group->first()->id);
            }

            $sourceId = $outbound->pluck('location_id')->unique();
            $destinationId = $inbound->pluck('location_id')->unique();
            $itemIds = $group->pluck('item_id')->unique();

            if ($sourceId->count() !== 1 || $destinationId->count() !== 1 || $itemIds->count() !== 1) {
                throw ReversalException::inconsistentMovement($group->first()->id);
            }

            $movements[] = [
                'item_id'        => $itemIds->first(),
                'location_id'    => $destinationId->first(),
                'to_location_id' => $sourceId->first(),
                'quantity'       => (string) $inbound->sum('quantity'),
                'unit_code'      => $inbound->first()->unit_code,
                'strategy'       => DeductionStrategy::Manual->value,
                'stock_ids'      => $inbound->pluck('stock_id')->unique()->values()->all(),
            ];

            $batches[] = [
                'link_id'  => $linkId,
                'outbound' => $inbound->map(fn (InventoryMovement $movement): array => [
                    'movement_id'   => $movement->id,
                    'stock_id'      => $movement->stock_id,
                    'item_id'       => $movement->item_id,
                    'location_id'   => $movement->location_id,
                    'quantity'      => $movement->quantity,
                    'unit_code'     => $movement->unit_code,
                    'total_cost'    => $movement->total_cost,
                    'currency_code' => $movement->currency_code,
                    'metadata'      => $movement->metadata,
                ])->values()->all(),
                'inbound' => $outbound->map(fn (InventoryMovement $movement): array => [
                    'movement_id'     => $movement->id,
                    'item_id'         => $movement->item_id,
                    'location_id'     => $movement->location_id,
                    'quantity'        => $movement->quantity,
                    'unit_code'       => $movement->unit_code,
                    'total_cost'      => $movement->total_cost,
                    'currency_code'   => $movement->currency_code,
                    'expiration_date' => $movement->expiration_date,
                    'metadata'        => $movement->metadata,
                    'stock_metadata'  => $movement->stock_metadata_snapshot,
                ])->values()->all(),
            ];
        }

        return [
            'idempotency_key'            => $original->id.':reverse',
            'reference_type'             => $original->reference_type,
            'reference_id'               => $original->reference_id,
            'transaction_type'           => TransactionType::Transfer->value,
            'metadata'                   => $metadata,
            'movements'                  => $movements,
            '_transfer_reversal_batches' => $batches,
        ];
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
                ->flatMap(function (mixed $movement): array {
                    if (!is_array($movement)) {
                        return [];
                    }

                    return [
                        $movement['location'] ?? $movement['location_id'] ?? null,
                        $movement['to_location'] ?? $movement['to_location_id'] ?? null,
                    ];
                })
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

            foreach (['location', 'to_location'] as $locationField) {
                $locationReference = $movement[$locationField] ?? $movement[$locationField.'_id'] ?? null;
                if (!$locationReference instanceof HasInventoryLocation) {
                    continue;
                }

                $locationKey = $locationReference->getMorphClass().':'.(string) $locationReference->getKey();
                $movement[$locationField.'_id'] = $resolvedLocations->get($locationKey)?->id;
                unset($movement[$locationField]);
            }

            return $movement;
        })->values()->all();

        return $data;
    }

    /**
     * @param  Collection<string, InventoryItem>  $items
     * @return Collection<int, MovementExecutionContextData>
     */
    protected function buildMovementContexts(TransactionData $transaction, Collection $items): Collection
    {
        return $transaction->movements->map(function (MovementData $movement) use ($items): MovementExecutionContextData {
            $item = $items->get($movement->itemId);

            return MovementExecutionContextData::fromArray([
                'movement'         => $movement,
                'item'             => $item,
                'quantity_in_base' => $this->masterInterface->convertUnit(
                    $movement->quantity,
                    $movement->unitCode,
                    $item->base_unit_code
                ),
            ]);
        });
    }

    /**
     * @param  Collection<int, MovementExecutionContextData>  $movementContexts
     */
    protected function processInMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        foreach ($movementContexts as $context) {
            $this->applyInbound(
                tx: $tx,
                context: $context,
                stockMetadata: $context->movement->stockMetadata,
            );
        }
    }

    /**
     * @param  Collection<int, MovementExecutionContextData>  $movementContexts
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
     * @param  Collection<int, MovementExecutionContextData>  $movementContexts
     */
    protected function processAdjustmentMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        foreach ($movementContexts as $index => $context) {
            $movement = $context->movement;
            $item = $context->item;
            $targetQtyInBase = $context->quantityInBase;
            $currentStocks = $this->resolveStocksForAdjustment($movement);

            $currentQtyInBase = (string) $currentStocks->sum('remaining');
            $delta = bcsub($targetQtyInBase, $currentQtyInBase, 10);
            $cmp = bccomp($delta, '0', 10);

            if ($cmp > 0) {
                if ($item->is_expirable && $movement->expirationDate === null) {
                    throw ValidationException::withMessages([
                        "movements.{$index}.expiration_date" => __('inventory::validation.expiration_date_required_for_expirable_item'),
                    ]);
                }

                if (!$item->is_expirable && $movement->expirationDate !== null) {
                    throw ValidationException::withMessages([
                        "movements.{$index}.expiration_date" => __('inventory::validation.expiration_date_prohibited_for_non_expirable_item'),
                    ]);
                }

                if ($movement->currencyCode === null) {
                    throw AdjustmentAverageCostUnavailableException::currencyRequired(
                        $movement->itemId,
                        $movement->locationId
                    );
                }

                $averageCost = $this->resolveAdjustmentAverageCost(
                    stocks: $currentStocks,
                    currencyCode: $movement->currencyCode,
                    quantityToAdd: $delta,
                    itemId: $movement->itemId,
                    locationId: $movement->locationId,
                );

                $this->applyInbound(
                    tx: $tx,
                    context: $context,
                    quantityOverrideInBase: $delta,
                    totalCost: $averageCost,
                    currencyCode: $movement->currencyCode,
                    stockMetadata: $movement->stockMetadata,
                );
            } elseif ($cmp < 0) {
                $stocks = $this->resolveStocksForDeduction($movement, $item);
                $this->applyDeduction(
                    tx: $tx,
                    context: $context,
                    quantityToDeduct: bcsub('0', $delta, 10),
                    stocks: $stocks,
                );
            } else {
                throw new AdjustmentNoOpException($movement->itemId, $movement->locationId);
            }
        }
    }

    /**
     * @param  Collection<int, MovementExecutionContextData>  $movementContexts
     */
    protected function processTransferMovements(InventoryTransaction $tx, Collection $movementContexts): void
    {
        foreach ($movementContexts as $context) {
            $linkId = Str::uuid()->toString();
            $destinationContext = $this->buildTransferDestinationContext($context);
            $deductedMovements = $this->applyDeduction(
                tx: $tx,
                context: $context,
                quantityToDeduct: $context->quantityInBase,
                linkId: $linkId,
            );

            foreach ($deductedMovements as $deductedMovement) {
                $this->applyInbound(
                    tx: $tx,
                    context: $destinationContext,
                    quantityOverrideInBase: (string) $deductedMovement->quantity,
                    totalCost: $deductedMovement->total_cost,
                    currencyCode: $deductedMovement->currency_code,
                    stockMetadata: $deductedMovement->stock_metadata_snapshot,
                    linkId: $linkId,
                );
            }
        }
    }

    /**
     * Replays persisted transfer allocations when reversing a transfer.
     *
     * @param  array<int, array{outbound: array<int, array<string, mixed>>, inbound: array<int, array<string, mixed>>}>  $batches
     * @param  Collection<int, MovementExecutionContextData>  $movementContexts
     */
    protected function processTransferReversalMovements(
        InventoryTransaction $tx,
        array $batches,
        Collection $movementContexts,
    ): void {
        foreach ($batches as $batch) {
            $linkId = Str::uuid()->toString();

            foreach ($batch['outbound'] as $movementData) {
                $template = $movementContexts->first(
                    fn (MovementExecutionContextData $context): bool => $context->movement->itemId === $movementData['item_id']
                        && $context->movement->locationId === $movementData['location_id']
                );

                if (!$template) {
                    throw ReversalException::inconsistentMovement((string) $movementData['movement_id']);
                }

                $this->applyExactReversalDeduction($tx, $movementData, $linkId);
            }

            foreach ($batch['inbound'] as $movementData) {
                $template = $movementContexts->first(
                    fn (MovementExecutionContextData $context): bool => $context->movement->itemId === $movementData['item_id']
                );

                if (!$template || $movementData['currency_code'] === null) {
                    throw ReversalException::inconsistentMovement((string) $movementData['movement_id']);
                }

                $context = MovementExecutionContextData::fromArray([
                    'movement' => MovementData::fromArray([
                        'item_id'         => $movementData['item_id'],
                        'location_id'     => $movementData['location_id'],
                        'type'            => MovementType::In,
                        'quantity'        => (string) $movementData['quantity'],
                        'unit_code'       => $movementData['unit_code'],
                        'total_cost'      => (int) $movementData['total_cost'],
                        'currency_code'   => $movementData['currency_code'],
                        'expiration_date' => $movementData['expiration_date'],
                        'metadata'        => $movementData['metadata'],
                        'stock_metadata'  => $movementData['stock_metadata'],
                    ], $this->masterInterface, costsInMinor: true),
                    'item'             => $template->item,
                    'quantity_in_base' => (string) $movementData['quantity'],
                ]);

                $this->applyInbound(
                    tx: $tx,
                    context: $context,
                    totalCost: (int) $movementData['total_cost'],
                    currencyCode: $movementData['currency_code'],
                    stockMetadata: $movementData['stock_metadata'],
                    linkId: $linkId,
                );
            }
        }
    }

    protected function buildTransferDestinationContext(MovementExecutionContextData $context): MovementExecutionContextData
    {
        $movement = $context->movement;
        $destinationMovement = MovementData::fromArray([
            'item_id'     => $movement->itemId,
            'location_id' => $movement->toLocationId,
            'type'        => MovementType::In,
            'quantity'    => $movement->quantity,
            'unit_code'   => $movement->unitCode,
            'metadata'    => $movement->metadata,
        ], $this->masterInterface);

        return MovementExecutionContextData::fromArray([
            'movement'         => $destinationMovement,
            'item'             => $context->item,
            'quantity_in_base' => $context->quantityInBase,
        ]);
    }

    protected function applyInbound(
        InventoryTransaction $tx,
        MovementExecutionContextData $context,
        ?string $quantityOverrideInBase = null,
        ?int $totalCost = null,
        ?string $currencyCode = null,
        ?array $stockMetadata = null,
        ?string $linkId = null,
    ): InventoryStock {
        $organizationId = currentOrganizationId();
        $movement = $context->movement;
        $item = $context->item;
        $quantityInBase = $quantityOverrideInBase ?? $context->quantityInBase;
        $quantity = (int) $quantityInBase;
        $resolvedCurrencyCode = $currencyCode ?? $movement->currencyCode;
        $resolvedTotalCost = $totalCost ?? $movement->totalCost;

        if ($resolvedTotalCost === null) {
            throw new InboundCostRequiredException($movement->itemId, $movement->locationId);
        }

        $resolvedUnitCost = intdiv($resolvedTotalCost, $quantity);
        $costRemainder = $resolvedTotalCost % $quantity;

        $stock = InventoryStock::create([
            'organization_id' => $organizationId,
            'item_id'         => $movement->itemId,
            'location_id'     => $movement->locationId,
            'unit_cost'       => $resolvedUnitCost,
            'cost_remainder'  => $costRemainder,
            'currency_code'   => $resolvedCurrencyCode,
            'quantity'        => $quantity,
            'remaining'       => $quantity,
            'unit_code'       => $item->base_unit_code,
            'expiration_date' => $movement->expirationDate,
            'metadata'        => $stockMetadata,
        ]);

        InventoryMovement::create([
            'organization_id' => $organizationId,
            'movement_type'   => MovementType::In,
            'transaction_id'  => $tx->id,
            'link_id'         => $linkId,
            'item_id'         => $movement->itemId,
            'location_id'     => $movement->locationId,
            'stock_id'        => $stock->id,
            'quantity'        => $quantity,
            'unit_code'       => $item->base_unit_code,
            'total_cost'      => $resolvedTotalCost,
            'currency_code'   => $resolvedCurrencyCode,
            'expiration_date' => $movement->expirationDate,
            'metadata'        => $movement->metadata,
        ]);

        return $stock;
    }

    protected function applyDeduction(
        InventoryTransaction $tx,
        MovementExecutionContextData $context,
        string $quantityToDeduct,
        ?Collection $stocks = null,
        ?string $linkId = null
    ): Collection {
        $movement = $context->movement;
        $item = $context->item;

        $stocks ??= $this->resolveStocksForDeduction($movement, $item);

        $totalAvailable = (string) $stocks->sum('remaining');

        if (bccomp($quantityToDeduct, $totalAvailable, 10) > 0) {
            throw new InsufficientStockException(
                $movement->itemId,
                $movement->locationId,
                $this->masterInterface->convertUnit(
                    $quantityToDeduct,
                    $item->base_unit_code,
                    $movement->unitCode
                ),
                $this->masterInterface->convertUnit($totalAvailable, $item->base_unit_code, $movement->unitCode),
                $movement->unitCode
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
            $stockMetadataSnapshot = $stock->metadata;
            $deduction = bccomp($remainingToDeduct, $stockRemaining, 10) >= 0
                ? $stockRemaining
                : $remainingToDeduct;

            $deductionQuantity = (int) $deduction;
            $stockCostRemainder = $stock->cost_remainder;
            $allocatedCostRemainder = $deductionQuantity > 0 ? $stockCostRemainder : 0;
            $remainingCostRemainder = $stockCostRemainder - $allocatedCostRemainder;
            $deductedTotalCost = ($deductionQuantity * $stock->unit_cost) + $allocatedCostRemainder;

            $stock->remaining = (int) bcsub($stockRemaining, $deduction, 0);
            $stock->cost_remainder = $remainingCostRemainder;
            $stock->save();

            $outMovement = InventoryMovement::create([
                'organization_id'         => currentOrganizationId(),
                'movement_type'           => MovementType::Out,
                'transaction_id'          => $tx->id,
                'link_id'                 => $linkId,
                'item_id'                 => $movement->itemId,
                'location_id'             => $movement->locationId,
                'stock_id'                => $stock->id,
                'quantity'                => (int) $deduction,
                'unit_code'               => $item->base_unit_code,
                'total_cost'              => $deductedTotalCost,
                'currency_code'           => $stock->currency_code,
                'expiration_date'         => $stock->expiration_date,
                'metadata'                => $movement->metadata,
                'stock_metadata_snapshot' => $stockMetadataSnapshot,
            ]);

            $outMovement->setRelation('stock', $stock);
            $movements->push($outMovement);

            $remainingToDeduct = bcsub($remainingToDeduct, $deduction, 10);
        }

        return $movements;
    }

    /**
     * Deducts the persisted inbound allocation of a transfer without recalculating
     * its historical cost from the current stock state.
     *
     * @param  array<string, mixed>  $movementData
     */
    protected function applyExactReversalDeduction(
        InventoryTransaction $tx,
        array $movementData,
        string $linkId,
    ): void {
        $stock = InventoryStock::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($movementData['stock_id'])
            ->lockForUpdate()
            ->first();

        if (!$stock || $stock->item_id !== $movementData['item_id'] || $stock->location_id !== $movementData['location_id']) {
            throw ReversalException::inconsistentMovement((string) $movementData['movement_id']);
        }

        if ((int) $stock->remaining < (int) $movementData['quantity']) {
            throw ReversalException::stockAlreadyUsed(
                movementId: (string) $movementData['movement_id'],
                stockId: (string) $stock->id,
                requested: (string) $movementData['quantity'],
                remaining: (string) $stock->remaining,
            );
        }

        $stock->remaining -= (int) $movementData['quantity'];
        $stock->cost_remainder = 0;
        $stock->save();

        $outMovement = InventoryMovement::create([
            'organization_id'         => currentOrganizationId(),
            'movement_type'           => MovementType::Out,
            'transaction_id'          => $tx->id,
            'link_id'                 => $linkId,
            'item_id'                 => $movementData['item_id'],
            'location_id'             => $movementData['location_id'],
            'stock_id'                => $stock->id,
            'quantity'                => (int) $movementData['quantity'],
            'unit_code'               => $movementData['unit_code'],
            'total_cost'              => (int) $movementData['total_cost'],
            'currency_code'           => $movementData['currency_code'],
            'expiration_date'         => $stock->expiration_date,
            'metadata'                => $movementData['metadata'],
            'stock_metadata_snapshot' => $stock->metadata,
        ]);

        $outMovement->setRelation('stock', $stock);
    }

    protected function resolveStocksForAdjustment(MovementData $movement): Collection
    {
        return InventoryStock::query()
            ->where('item_id', $movement->itemId)
            ->where('organization_id', currentOrganizationId())
            ->where('location_id', $movement->locationId)
            ->where('remaining', '>', 0)
            ->lockForUpdate()
            ->get();
    }

    protected function resolveAdjustmentAverageCost(
        Collection $stocks,
        string $currencyCode,
        string $quantityToAdd,
        string $itemId,
        string $locationId,
    ): int {
        $currencyStocks = $stocks->where('currency_code', $currencyCode);
        $currentQuantity = '0';
        $currentValue = '0';

        foreach ($currencyStocks as $stock) {
            $currentQuantity = bcadd($currentQuantity, (string) $stock->remaining, 0);
            $stockValue = bcadd(
                bcmul((string) $stock->remaining, (string) $stock->unit_cost, 0),
                (string) $stock->cost_remainder,
                0
            );
            $currentValue = bcadd($currentValue, $stockValue, 0);
        }

        if (bccomp($currentQuantity, '0', 0) <= 0) {
            throw AdjustmentAverageCostUnavailableException::stockUnavailable($itemId, $locationId, $currencyCode);
        }

        return (int) bcdiv(
            bcmul($currentValue, $quantityToAdd, 0),
            $currentQuantity,
            0
        );
    }

    protected function resolveStocksForDeduction(MovementData $movement, InventoryItem $item): Collection
    {
        $strategy = $this->resolveDeductionStrategy($movement, $item);
        $cacheKey = $this->buildStockSelectionCacheKey($movement, $strategy);

        if (isset($this->stockSelectionCache[$cacheKey])) {
            return $this->stockSelectionCache[$cacheKey];
        }

        $query = InventoryStock::where('item_id', $movement->itemId)
            ->where('organization_id', currentOrganizationId())
            ->where('location_id', $movement->locationId)
            ->where('remaining', '>', 0)
            ->lockForUpdate();

        $stocks = match ($strategy) {
            DeductionStrategy::Fifo => $query->orderBy('created_at', 'asc')->get(),
            DeductionStrategy::Fefo => $query->orderByRaw('expiration_date ASC NULLS LAST')
                ->orderBy('created_at', 'asc')->get(),
            DeductionStrategy::Manual => $this->orderStocksManually(
                $query->whereIn('id', $movement->stockIds)->get(),
                $movement->stockIds ?? [],
            ),
        };

        return $this->stockSelectionCache[$cacheKey] = $stocks;
    }

    protected function resolveDeductionStrategy(MovementData $movement, InventoryItem $item): DeductionStrategy
    {
        return $movement->strategy
            ?? $item->deduction_strategy
            ?? ($item->is_expirable ? DeductionStrategy::Fefo : DeductionStrategy::Fifo);
    }

    protected function buildStockSelectionCacheKey(MovementData $movement, DeductionStrategy $strategy): string
    {
        return implode('|', [
            $movement->itemId,
            $movement->locationId,
            $strategy->value,
            implode(',', $movement->stockIds ?? []),
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
