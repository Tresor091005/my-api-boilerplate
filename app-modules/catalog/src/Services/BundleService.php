<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Assertions\BundleAssertion;
use Lahatre\Catalog\Data\BundleData;
use Lahatre\Catalog\Data\BundleFilterData;
use Lahatre\Catalog\Data\BundleItemData;
use Lahatre\Catalog\Data\BundleItemQuantityData;
use Lahatre\Catalog\Data\CatalogItemUpdateData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Exceptions\BundleException;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\HandleGenerator;

final readonly class BundleService
{
    public function __construct(
        private BundleAssertion $bundleAssertion,
        private TransactionalCatalogItemService $transactionalCatalogItemService,
        private MasterInterface $masterInterface,
    ) {}

    public function paginate(BundleFilterData $filters): CursorPaginator
    {
        $query = $this->bundlesQuery($filters);
        $query->with($this->bundleResponseRelations());

        return stableCursorPaginate(
            $query,
            $filters,
            tieBreakerColumn: 'catalog_bundles.id',
        );
    }

    public function retrieve(Bundle $bundle): Bundle
    {
        $this->assertTenant($bundle);

        return $bundle->load($this->bundleResponseRelations());
    }

    public function create(BundleData $data): Bundle
    {
        $organizationId = currentOrganizationId();
        $name = required($data->name);
        $itemsData = required($data->items);

        $bundle = DB::transaction(function () use ($data, $organizationId, $name, $itemsData): Bundle {
            $this->bundleAssertion->assertCanCreate($itemsData);
            $unitGroup = $this->resolveBundleUnitGroup();
            [$catalogItems, $units] = $this->resolveItemEvidence($organizationId, $itemsData);
            $this->bundleAssertion->assertItemsCanBeAttached(
                $itemsData,
                $catalogItems,
                $units,
                collect(),
            );

            $catalogItem = $this->transactionalCatalogItemService->createItem(
                CatalogItemType::Bundle,
                $organizationId,
                $name,
                required($data->sku),
                $unitGroup->id,
                required($data->isActive),
                $data->inventory,
            );

            $bundle = new Bundle;
            $bundle->forceFill([
                'id'              => $catalogItem->id,
                'organization_id' => $organizationId,
                'handle'          => HandleGenerator::generate(
                    $name,
                    $bundle->getTable(),
                    extra: ['organization_id' => $organizationId],
                ),
                'name' => $name,
            ])->save();

            $this->insertItems($bundle, $itemsData);

            return $bundle;
        });

        return $bundle->load($this->bundleResponseRelations());
    }

    public function update(Bundle $bundle, BundleData $data): Bundle
    {
        $organizationId = currentOrganizationId();

        $bundle = DB::transaction(function () use ($bundle, $data, $organizationId): Bundle {
            $lockedBundle = $this->lockBundle($bundle, $organizationId);

            /** @var CatalogItem $catalogItem */
            $catalogItem = $lockedBundle->catalogItem()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBundle->fill(withoutMissing(['name' => $data->name]));
            $lockedBundle->save();

            try {
                $this->transactionalCatalogItemService->update(
                    $catalogItem,
                    new CatalogItemUpdateData(
                        sku: $data->sku ?? MissingValue::Instance,
                        isActive: $data->isActive,
                        inventory: $data->inventory,
                    ),
                );
            } catch (ValidationException $exception) {
                $errors = collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $field): array => ["inventory.{$field}" => $messages])
                    ->all();

                throw ValidationException::withMessages($errors);
            }

            return $lockedBundle;
        });

        return $bundle->load($this->bundleResponseRelations());
    }

    /**
     * @param  Collection<int, BundleItemData>  $itemsData
     * @return EloquentCollection<int, BundleItem>
     */
    public function addItems(Bundle $bundle, Collection $itemsData): EloquentCollection
    {
        $organizationId = currentOrganizationId();

        $items = DB::transaction(function () use ($bundle, $itemsData, $organizationId): EloquentCollection {
            $lockedBundle = $this->lockBundle($bundle, $organizationId);
            [$catalogItems, $units] = $this->resolveItemEvidence($organizationId, $itemsData);
            $existingItemIds = $lockedBundle->items()
                ->whereIn('item_id', $itemsData->pluck('itemId')->all())
                ->lockForUpdate()
                ->pluck('item_id');

            $this->bundleAssertion->assertItemsCanBeAttached(
                $itemsData,
                $catalogItems,
                $units,
                $existingItemIds,
            );

            return $this->insertItems($lockedBundle, $itemsData);
        });

        return $items->load($this->bundleResponseRelations());
    }

    public function updateItem(
        Bundle $bundle,
        BundleItem $bundleItem,
        BundleItemQuantityData $data,
    ): BundleItem {
        $organizationId = currentOrganizationId();

        $bundleItem = DB::transaction(function () use ($bundle, $bundleItem, $data, $organizationId): BundleItem {
            $lockedBundle = $this->lockBundle($bundle, $organizationId);

            /** @var BundleItem $lockedItem */
            $lockedItem = BundleItem::query()
                ->where('organization_id', $organizationId)
                ->where('bundle_id', $lockedBundle->id)
                ->whereKey($bundleItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->bundleAssertion->assertItemBelongsToBundle($lockedBundle, $lockedItem);
            $this->bundleAssertion->assertQuantityIsPositive($data->quantity);

            $catalogItem = CatalogItem::query()
                ->where('organization_id', $organizationId)
                ->whereKey($lockedItem->item_id)
                ->first();
            if (!$catalogItem instanceof CatalogItem) {
                throw BundleException::itemsUnavailable([$lockedItem->item_id]);
            }

            $unit = $this->masterInterface->unit($data->unitCode);
            $this->bundleAssertion->assertUnitCanRepresentItem($catalogItem, $unit, $data->unitCode);

            $baseQuantity = $this->masterInterface->convertUnitToBase((string) $data->quantity, $data->unitCode);
            $lockedItem->quantity = (int) $baseQuantity['amount'];
            $lockedItem->display_unit_code = $data->unitCode;
            $lockedItem->save();

            return $lockedItem;
        });

        return $bundleItem->load($this->bundleResponseRelations());
    }

    /** @param list<string> $itemIds */
    public function removeItems(Bundle $bundle, array $itemIds): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($bundle, $itemIds, $organizationId): void {
            $lockedBundle = $this->lockBundle($bundle, $organizationId);
            $bundleItems = $lockedBundle->items()->lockForUpdate()->get(['id']);

            $requestedItemIds = collect($itemIds)->unique()->values();
            $itemIdsToRemove = $bundleItems->whereIn('id', $requestedItemIds)->values()->pluck('id');

            $missingItemIds = $requestedItemIds
                ->reject(fn (string $itemId): bool => $itemIdsToRemove->contains($itemId))
                ->values()
                ->all();

            if ($missingItemIds !== []) {
                throw BundleException::itemsUnavailable($missingItemIds);
            }

            $remainingItems = $bundleItems->count() - $itemIdsToRemove->count();
            $this->bundleAssertion->assertCanRemoveItems($lockedBundle, $remainingItems);

            $lockedBundle->items()->whereIn('id', $requestedItemIds)->delete();
        });
    }

    public function delete(Bundle $bundle): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($bundle, $organizationId): void {
            $lockedBundle = $this->lockBundle($bundle, $organizationId);

            /** @var CatalogItem $catalogItem */
            $catalogItem = $lockedBundle->catalogItem()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBundle->items()->delete();
            $lockedBundle->delete();
            $this->transactionalCatalogItemService->delete($catalogItem);
        });
    }

    /** @return Builder<Bundle> */
    private function bundlesQuery(BundleFilterData $filters): Builder
    {
        $organizationId = currentOrganizationId();

        /** @var Builder<Bundle> $query */
        $query = Bundle::query();
        $query->join('catalog_items', function (JoinClause $join): void {
            $join->on('catalog_items.id', '=', 'catalog_bundles.id')
                ->on('catalog_items.organization_id', '=', 'catalog_bundles.organization_id');
        })
            ->where('catalog_bundles.organization_id', $organizationId)
            ->where('catalog_items.organization_id', $organizationId)
            ->where('catalog_items.item_type', CatalogItemType::Bundle->value)
            ->whereNull('catalog_items.deleted_at')
            ->select([
                'catalog_bundles.*',
                'catalog_items.sku as catalog_item_sku',
                'catalog_items.is_active as catalog_item_is_active',
            ]);

        if ($filters->handle !== null) {
            $query->where('catalog_bundles.handle', 'like', "{$filters->handle}%");
        }
        if ($filters->name !== null) {
            $query->where('catalog_bundles.name', 'like', "{$filters->name}%");
        }
        if ($filters->sku !== null) {
            $query->where('catalog_items.sku', 'like', "{$filters->sku}%");
        }
        if ($filters->isActive !== null) {
            $query->where('catalog_items.is_active', $filters->isActive);
        }

        return $query;
    }

    /**
     * Add component-specific eager loads required by catalog resources.
     *
     * @return array<int|string, mixed>
     */
    private function bundleResponseRelations(): array
    {
        $relations = responseRelationsToLoad();
        $componentLoader = function (MorphTo $relation): void {
            $relation->morphWith([
                ProductVariant::class => [
                    'catalogItem',
                    'product',
                    'optionValues.option',
                ],
            ]);
        };

        foreach (['component', 'items.component'] as $componentRelation) {
            $relationIndex = array_search($componentRelation, $relations, true);

            if ($relationIndex === false) {
                continue;
            }

            unset($relations[$relationIndex]);
            $relations[$componentRelation] = $componentLoader;
        }

        return $relations;
    }

    private function resolveBundleUnitGroup(): UnitGroup
    {
        try {
            $bundleUnit = $this->masterInterface->unit('bundle')->load('group');
        } catch (ModelNotFoundException) {
            throw BundleException::systemUnitGroupMissing();
        }

        $group = $bundleUnit->getRelation('group');

        if ($bundleUnit->organization_id !== null
            || !$group instanceof UnitGroup
            || $group->organization_id !== null
            || !$group->is_builtin
        ) {
            throw BundleException::systemUnitGroupMissing();
        }

        return $group;
    }

    /**
     * @param  Collection<int, BundleItemData>  $itemsData
     * @return array{EloquentCollection<string, CatalogItem>, Collection<string, Unit>}
     */
    private function resolveItemEvidence(string $organizationId, Collection $itemsData): array
    {
        /** @var EloquentCollection<string, CatalogItem> $catalogItems */
        $catalogItems = CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $itemsData->pluck('itemId')->unique()->all())
            ->get()
            ->keyBy('id');

        $units = $this->masterInterface->units($itemsData->pluck('unitCode')->unique()->values());

        return [$catalogItems, $units];
    }

    /**
     * @param  Collection<int, BundleItemData>  $itemsData
     * @return EloquentCollection<int, BundleItem>
     */
    private function insertItems(Bundle $bundle, Collection $itemsData): EloquentCollection
    {
        $now = now();
        $ids = [];
        $rows = [];

        foreach ($itemsData as $itemData) {
            $id = (string) Str::uuid7();
            $ids[] = $id;
            $rows[] = [
                'id'              => $id,
                'organization_id' => $bundle->organization_id,
                'bundle_id'       => $bundle->id,
                'item_type'       => $itemData->itemType->value,
                'item_id'         => $itemData->itemId,
                'quantity'        => (int) $this->masterInterface
                    ->convertUnitToBase((string) $itemData->quantity, $itemData->unitCode)['amount'],
                'display_unit_code' => $itemData->unitCode,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        BundleItem::insert($rows);

        return BundleItem::query()
            ->where('organization_id', $bundle->organization_id)
            ->where('bundle_id', $bundle->id)
            ->whereIn('id', $ids)
            ->get();
    }

    private function lockBundle(Bundle $bundle, string $organizationId): Bundle
    {
        $this->assertTenant($bundle);

        /** @var Bundle $model */
        $model = Bundle::query()
            ->where('organization_id', $organizationId)
            ->whereKey($bundle->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $model;
    }

    private function assertTenant(Bundle $bundle): void
    {
        if ($bundle->organization_id !== currentOrganizationId()) {
            throw (new ModelNotFoundException)->setModel(Bundle::class, [$bundle->id]);
        }
    }
}
