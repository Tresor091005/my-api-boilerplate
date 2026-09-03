<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Data\BundleStockOperationComponentData;
use Lahatre\Catalog\Data\BundleStockOperationData;
use Lahatre\Catalog\Data\BundleStockOperationFilterData;
use Lahatre\Catalog\Enums\BundleStockOperationStatus;
use Lahatre\Catalog\Enums\BundleStockOperationType;
use Lahatre\Catalog\Exceptions\BundleException;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\BundleStockOperation;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Master\Contracts\MasterInterface;

final readonly class BundleStockOperationService
{
    public function __construct(
        private InventoryInterface $inventoryInterface,
        private MasterInterface $masterInterface,
    ) {}

    /**
     * Create an unexecuted bundle stock operation draft.
     *
     * The caller owns HTTP authorization and organization context. This method
     * snapshots the current composition but does not mutate inventory.
     */
    public function create(Bundle $bundle, BundleStockOperationData $data): BundleStockOperation
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($bundle, $data, $organizationId): BundleStockOperation {
            $lockedBundle = $this->lockBundleWithEvidence($bundle, $organizationId);
            $prepared = $this->validateAndPrepareOperation($lockedBundle, $data);

            $operation = new BundleStockOperation;
            $operation->forceFill([
                'id'                   => (string) Str::uuid7(),
                'organization_id'      => $organizationId,
                'bundle_id'            => $lockedBundle->id,
                'type'                 => $data->type,
                'status'               => BundleStockOperationStatus::Draft,
                'quantity'             => $data->quantity,
                'location_id'          => $data->locationId,
                'payload'              => $data->toPayload(),
                'composition_snapshot' => $prepared['snapshot'],
            ])->save();

            return $operation;
        });
    }

    /**
     * Complete a bundle stock operation draft.
     *
     * The operation owns one transaction that contains the outgoing and
     * incoming inventory transactions. Inventory is unchanged when any
     * validation or movement fails.
     */
    public function complete(Bundle $bundle, string $operationId): BundleStockOperation
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($bundle, $operationId, $organizationId): BundleStockOperation {
            $lockedBundle = $this->lockBundleWithEvidence($bundle, $organizationId);
            /** @var BundleStockOperation $operation */
            $operation = BundleStockOperation::query()
                ->where('organization_id', $organizationId)
                ->where('bundle_id', $lockedBundle->id)
                ->whereKey($operationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($operation->status === BundleStockOperationStatus::Completed) {
                return $operation;
            }

            $data = BundleStockOperationData::fromArray($operation->payload);
            $prepared = $this->validateAndPrepareOperation($lockedBundle, $data, $operation);
            $inventoryLocationId = $prepared['stockLocation']->inventoryLocation->id;

            $outTransaction = $this->recordOutgoingTransaction($lockedBundle, $operation, $data, $inventoryLocationId);
            $inTransaction = $this->recordIncomingTransaction($lockedBundle, $operation, $data, $inventoryLocationId, $outTransaction->movements);

            $operation->forceFill([
                'status'             => BundleStockOperationStatus::Completed,
                'out_transaction_id' => $outTransaction->id,
                'in_transaction_id'  => $inTransaction->id,
                'completed_at'       => now(),
            ])->save();

            return $operation->fresh();
        });
    }

    public function paginate(Bundle $bundle, BundleStockOperationFilterData $filters): CursorPaginator
    {
        $organizationId = currentOrganizationId();
        $query = BundleStockOperation::query()
            ->where('organization_id', $organizationId)
            ->where('bundle_id', $bundle->id)
            ->when($filters->type, fn ($query, $type) => $query->where('type', $type))
            ->when($filters->status, fn ($query, $status) => $query->where('status', $status));

        return stableCursorPaginate(
            $query,
            $filters,
            tieBreakerColumn: 'catalog_bundle_stock_operations.id',
        );
    }

    public function retrieve(Bundle $bundle, string $operationId): BundleStockOperation
    {
        $organizationId = currentOrganizationId();

        return BundleStockOperation::query()
            ->where('organization_id', $organizationId)
            ->where('bundle_id', $bundle->id)
            ->whereKey($operationId)
            ->firstOrFail();
    }

    /**
     * @return EloquentCollection<int, BundleItem>
     */
    private function lockBundleItems(Bundle $bundle): EloquentCollection
    {
        /** @var EloquentCollection<int, BundleItem> $items */
        $items = $bundle->items()
            ->with(['catalogItem.inventoryItem'])
            ->lockForUpdate()
            ->get();

        return $items;
    }

    private function lockBundleWithEvidence(Bundle $bundle, string $organizationId): Bundle
    {
        if ($bundle->organization_id !== $organizationId) {
            throw (new ModelNotFoundException)->setModel(Bundle::class, [$bundle->id]);
        }

        /** @var Bundle $lockedBundle */
        $lockedBundle = Bundle::query()
            ->where('organization_id', $organizationId)
            ->whereKey($bundle->id)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedBundle->load('catalogItem.inventoryItem');
        $lockedBundle->setRelation('items', $this->lockBundleItems($lockedBundle));

        return $lockedBundle;
    }

    private function assertOperationState(Bundle $bundle): void
    {
        /** @var list<array{code: string, context: array<string, string>}> $errors */
        $errors = [];
        /** @var CatalogItem|null $catalogItem */
        $catalogItem = $bundle->catalogItem;
        /** @var InventoryItem|null $inventoryItem */
        $inventoryItem = $catalogItem?->inventoryItem;

        if (!$catalogItem instanceof CatalogItem || !$catalogItem->is_active) {
            $errors[] = [
                'code'    => 'bundle_inactive',
                'context' => ['bundle_id' => $bundle->id],
            ];
        }

        if (!$inventoryItem instanceof InventoryItem || !$inventoryItem->stock_tracking_enabled) {
            $errors[] = [
                'code'    => 'bundle_tracking_disabled',
                'context' => ['bundle_id' => $bundle->id],
            ];
        }

        foreach ($bundle->items as $bundleItem) {
            /** @var CatalogItem|null $component */
            $component = $bundleItem->catalogItem;
            /** @var InventoryItem|null $componentInventory */
            $componentInventory = $component?->inventoryItem;

            if (!$component instanceof CatalogItem || !$component->is_active) {
                $errors[] = [
                    'code'    => 'component_inactive',
                    'context' => ['item_id' => $bundleItem->item_id],
                ];
            }

            if (!$componentInventory instanceof InventoryItem || !$componentInventory->stock_tracking_enabled) {
                $errors[] = [
                    'code'    => 'component_tracking_disabled',
                    'context' => ['item_id' => $bundleItem->item_id],
                ];
            }
        }

        if ($errors !== []) {
            throw BundleException::stockOperationInvalidState($errors);
        }
    }

    /**
     * @param  EloquentCollection<int, BundleItem>  $items
     * @param  Collection<int, BundleStockOperationComponentData>  $components
     */
    private function assertComponentsMatchComposition(
        EloquentCollection $items,
        Collection $components,
    ): void {
        $itemIds = $items->pluck('id')->sort()->values()->all();
        $componentIds = $components->pluck('bundleItemId')->sort()->values()->all();

        if ($itemIds !== $componentIds) {
            throw ValidationException::withMessages([
                'components' => __('catalog::validation.bundle_stock_operation_components_mismatch'),
            ]);
        }
    }

    private function validateOperationFields(Bundle $bundle, BundleStockOperationData $data): void
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];
        $catalogItem = $bundle->catalogItem;
        $bundleInventory = $catalogItem->inventoryItem;
        $bundleItemsById = $bundle->items->keyBy('id');

        if ($data->type === BundleStockOperationType::Attach) {
            if ($data->sourceStockIds !== []) {
                $this->addOperationFieldError(
                    $errors,
                    'stock_ids',
                    __('catalog::validation.bundle_stock_operation_stock_ids_prohibited'),
                );
            }

            $this->validateExpirationDate($data->expirationDate, $bundleInventory, 'expiration_date', $errors);

            foreach ($data->components as $component) {
                if ($component->expirationDate !== null) {
                    $this->addOperationFieldError(
                        $errors,
                        "components.{$component->bundleItemId}.expiration_date",
                        __('catalog::validation.bundle_stock_operation_expiration_prohibited'),
                    );
                }

                /** @var BundleItem $bundleItem */
                $bundleItem = $bundleItemsById->get($component->bundleItemId);
                $componentCatalogItem = $bundleItem->catalogItem;
                $componentInventory = $componentCatalogItem->inventoryItem;

                $this->validateStockIdsForDeductionStrategy(
                    $component->stockIds,
                    $componentInventory,
                    "components.{$component->bundleItemId}.stock_ids",
                    $errors,
                );
            }
        } else {
            if ($data->expirationDate !== null) {
                $this->addOperationFieldError(
                    $errors,
                    'expiration_date',
                    __('catalog::validation.bundle_stock_operation_expiration_prohibited'),
                );
            }

            $this->validateStockIdsForDeductionStrategy(
                $data->sourceStockIds,
                $bundleInventory,
                'stock_ids',
                $errors,
            );

            foreach ($data->components as $component) {
                /** @var BundleItem $bundleItem */
                $bundleItem = $bundleItemsById->get($component->bundleItemId);
                $componentCatalogItem = $bundleItem->catalogItem;
                $componentInventory = $componentCatalogItem->inventoryItem;

                if ($component->stockIds !== []) {
                    $this->addOperationFieldError(
                        $errors,
                        "components.{$component->bundleItemId}.stock_ids",
                        __('catalog::validation.bundle_stock_operation_stock_ids_prohibited'),
                    );
                }

                $this->validateExpirationDate(
                    $component->expirationDate,
                    $componentInventory,
                    "components.{$component->bundleItemId}.expiration_date",
                    $errors,
                );
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<string>  $stockIds
     * @param  array<string, list<string>>  $errors
     */
    private function validateStockIdsForDeductionStrategy(
        array $stockIds,
        InventoryItem $inventoryItem,
        string $path,
        array &$errors,
    ): void {
        if ($inventoryItem->deduction_strategy === DeductionStrategy::Manual && $stockIds === []) {
            $this->addOperationFieldError(
                $errors,
                $path,
                __('catalog::validation.bundle_stock_operation_manual_stock_ids_required'),
            );
        }

        if ($inventoryItem->deduction_strategy !== DeductionStrategy::Manual && $stockIds !== []) {
            $this->addOperationFieldError(
                $errors,
                $path,
                __('catalog::validation.bundle_stock_operation_stock_ids_strategy_prohibited'),
            );
        }
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function addOperationFieldError(array &$errors, string $path, string $message): void
    {
        $errors[$path][] = $message;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function validateExpirationDate(
        mixed $expirationDate,
        InventoryItem $inventoryItem,
        string $path,
        array &$errors,
    ): void {
        if ($inventoryItem->is_expirable && $expirationDate === null) {
            $this->addOperationFieldError(
                $errors,
                $path,
                __('catalog::validation.bundle_stock_operation_expiration_required'),
            );
        }

        if (!$inventoryItem->is_expirable && $expirationDate !== null) {
            $this->addOperationFieldError(
                $errors,
                $path,
                __('catalog::validation.bundle_stock_operation_expiration_prohibited'),
            );
        }
    }

    /**
     * @return array{snapshot: array<int, array<string, mixed>>, stockLocation: StockLocation}
     */
    private function validateAndPrepareOperation(
        Bundle $bundle,
        BundleStockOperationData $data,
        ?BundleStockOperation $operation = null,
    ): array {
        $snapshot = $this->compositionSnapshot($bundle->items);

        if ($operation !== null && $snapshot != $operation->composition_snapshot) {
            throw BundleException::stockOperationCompositionChanged($operation->id);
        }

        $this->assertOperationState($bundle);
        $this->assertComponentsMatchComposition($bundle->items, $data->components);
        $this->validateOperationFields($bundle, $data);

        return [
            'snapshot'      => $snapshot,
            'stockLocation' => $this->resolveStockLocation($data->locationId),
        ];
    }

    private function resolveStockLocation(string $stockLocationId): StockLocation
    {
        /** @var StockLocation $stockLocation */
        $stockLocation = StockLocation::query()
            ->where('organization_id', currentOrganizationId())
            ->whereKey($stockLocationId)
            ->lockForUpdate()
            ->firstOrFail();

        $stockLocation->load('inventoryLocation');
        if (!$stockLocation->inventoryLocation instanceof InventoryLocation) {
            throw (new ModelNotFoundException)->setModel(InventoryLocation::class, [$stockLocationId]);
        }

        if (!$stockLocation->inventoryLocation->is_active) {
            throw BundleException::stockOperationInvalidState([[
                'code'    => 'stock_location_inactive',
                'context' => ['location_id' => $stockLocationId],
            ]]);
        }

        return $stockLocation;
    }

    private function recordOutgoingTransaction(
        Bundle $bundle,
        BundleStockOperation $operation,
        BundleStockOperationData $data,
        string $inventoryLocationId,
    ): InventoryTransaction {
        $movements = [];
        $componentsByBundleItemId = $data->components->keyBy('bundleItemId');

        if ($data->type === BundleStockOperationType::Attach) {
            foreach ($bundle->items as $bundleItem) {
                $catalogItem = $bundleItem->catalogItem;
                $inventoryItem = $catalogItem->inventoryItem;
                /** @var BundleStockOperationComponentData $component */
                $component = $componentsByBundleItemId->get($bundleItem->id);

                $movements[] = [
                    'item_id'     => $inventoryItem->id,
                    'location_id' => $inventoryLocationId,
                    'type'        => MovementType::Out,
                    'quantity'    => (string) ($data->quantity * $bundleItem->quantity),
                    'unit_code'   => $inventoryItem->base_unit_code,
                    'stock_ids'   => $component->stockIds,
                    'strategy'    => $inventoryItem->deduction_strategy?->value,
                ];
            }
        } else {
            $catalogItem = $bundle->catalogItem;
            $inventoryItem = $catalogItem->inventoryItem;

            $movements[] = [
                'item_id'     => $inventoryItem->id,
                'location_id' => $inventoryLocationId,
                'type'        => MovementType::Out,
                'quantity'    => (string) $data->quantity,
                'unit_code'   => $inventoryItem->base_unit_code,
                'stock_ids'   => $data->sourceStockIds,
                'strategy'    => $inventoryItem->deduction_strategy?->value,
            ];
        }

        return $this->inventoryInterface->recordTransaction([
            'idempotency_key'  => "{$operation->id}:out",
            'reference_type'   => $operation->getMorphClass(),
            'reference_id'     => $operation->id,
            'transaction_type' => TransactionType::Out,
            'movements'        => $movements,
            'metadata'         => ['bundle_stock_operation_id' => $operation->id],
        ], ['movements']);
    }

    /**
     * @param  Collection<int, InventoryMovement>  $outMovements
     */
    private function recordIncomingTransaction(
        Bundle $bundle,
        BundleStockOperation $operation,
        BundleStockOperationData $data,
        string $inventoryLocationId,
        Collection $outMovements,
    ): InventoryTransaction {
        $currencyCodes = $outMovements->pluck('currency_code')->filter()->unique();

        if ($currencyCodes->count() !== 1) {
            throw BundleException::stockOperationCurrencyMismatch();
        }

        $currencyCode = $currencyCodes->first();
        $totalCostMinor = (int) $outMovements->sum('total_cost');

        if ($data->type === BundleStockOperationType::Attach) {
            $catalogItem = $bundle->catalogItem;
            $inventoryItem = $catalogItem->inventoryItem;

            $movements = [[
                'item_id'         => $inventoryItem->id,
                'location_id'     => $inventoryLocationId,
                'type'            => MovementType::In,
                'quantity'        => (string) $data->quantity,
                'unit_code'       => $inventoryItem->base_unit_code,
                'total_cost'      => $this->masterInterface->fromMinor((string) $totalCostMinor, $currencyCode),
                'currency_code'   => $currencyCode,
                'expiration_date' => $data->expirationDate?->toDateString(),
            ]];
        } else {
            $movements = $this->detachIncomingMovements($bundle, $data, $totalCostMinor, $currencyCode, $inventoryLocationId);
        }

        return $this->inventoryInterface->recordTransaction([
            'idempotency_key'  => "{$operation->id}:in",
            'reference_type'   => $operation->getMorphClass(),
            'reference_id'     => $operation->id,
            'transaction_type' => TransactionType::In,
            'movements'        => $movements,
            'metadata'         => ['bundle_stock_operation_id' => $operation->id],
        ], ['movements']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detachIncomingMovements(
        Bundle $bundle,
        BundleStockOperationData $data,
        int $totalCostMinor,
        string $currencyCode,
        string $inventoryLocationId,
    ): array {
        $denominator = $bundle->items->sum('quantity');
        $movements = [];
        $allocated = 0;
        $componentsByBundleItemId = $data->components->keyBy('bundleItemId');

        foreach ($bundle->items->values() as $index => $bundleItem) {
            $catalogItem = $bundleItem->catalogItem;
            $inventoryItem = $catalogItem->inventoryItem;
            /** @var BundleStockOperationComponentData $component */
            $component = $componentsByBundleItemId->get($bundleItem->id);
            $cost = $index === $bundle->items->count() - 1
                ? $totalCostMinor - $allocated
                : intdiv($totalCostMinor * $bundleItem->quantity, $denominator);
            $allocated += $cost;

            $movements[] = [
                'item_id'         => $inventoryItem->id,
                'location_id'     => $inventoryLocationId,
                'type'            => MovementType::In,
                'quantity'        => (string) ($data->quantity * $bundleItem->quantity),
                'unit_code'       => $inventoryItem->base_unit_code,
                'total_cost'      => $this->masterInterface->fromMinor((string) $cost, $currencyCode),
                'currency_code'   => $currencyCode,
                'expiration_date' => $component->expirationDate?->toDateString(),
            ];
        }

        if ($allocated !== $totalCostMinor) {
            throw BundleException::stockOperationCostAllocationMismatch();
        }

        return $movements;
    }

    /**
     * @param  EloquentCollection<int, BundleItem>  $items
     * @return array<int, array<string, mixed>>
     */
    private function compositionSnapshot(EloquentCollection $items): array
    {
        return $items->sortBy('id')->values()->map(fn (BundleItem $item): array => [
            'id'                => $item->id,
            'item_type'         => $item->item_type,
            'item_id'           => $item->item_id,
            'quantity'          => $item->quantity,
            'display_unit_code' => $item->display_unit_code,
        ])->values()->all();
    }
}
