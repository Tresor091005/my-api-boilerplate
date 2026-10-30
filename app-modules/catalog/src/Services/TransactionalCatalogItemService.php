<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\CatalogItemData;
use Lahatre\Catalog\Data\CatalogItemUpdateData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Exceptions\CatalogItemException;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Data\InventoryItemConfigurationData;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\SkuGenerator;
use Lahatre\Tax\Contracts\TaxInterface;

class TransactionalCatalogItemService
{
    public function __construct(
        protected InventoryInterface $inventoryService,
        protected TaxInterface $taxService,
    ) {}

    /**
     * Create a CatalogItem and its type-dependent operational representation.
     *
     * The caller owns the surrounding transaction.
     */
    public function createItem(
        CatalogItemType $type,
        string $organizationId,
        string $skuSource,
        ?string $sku,
        string $unitGroupId,
        bool $isActive,
        ?string $taxProfileId = null,
        ?InventoryItemConfigurationData $inventory = null,
    ): CatalogItem {
        if ($taxProfileId !== null) {
            $this->taxService->assertProfileAssignable($taxProfileId, collect([$unitGroupId]));
        }

        $catalogItem = new CatalogItem;
        $catalogItem->forceFill([
            'id'              => (string) Str::uuid7(),
            'organization_id' => $organizationId,
            'item_type'       => $type,
            'sku'             => $sku ?? SkuGenerator::generate($skuSource),
            'unit_group_id'   => $unitGroupId,
            'tax_profile_id'  => $taxProfileId,
            'is_stockable'    => $type->isStockable(),
            'is_active'       => $isActive,
        ])->save();

        if ($type->isStockable()) {
            $this->inventoryService->createItem($catalogItem, $inventory);
        }

        return $catalogItem;
    }

    /**
     * @param  Collection<int, CatalogItemData>  $itemsData
     * @return EloquentCollection<int, CatalogItem>
     */
    public function createManyProductVariants(
        string $organizationId,
        string $productName,
        Collection $itemsData,
    ): EloquentCollection {
        $this->assertTaxProfilesAssignable($itemsData);

        $now = now();
        $catalogItemRows = collect();
        $inventoryConfigurations = collect();

        foreach ($itemsData as $index => $itemData) {
            $id = (string) Str::uuid7();

            $catalogItemRows->put($index, [
                'id'              => $id,
                'organization_id' => $organizationId,
                'item_type'       => CatalogItemType::ProductVariant->value,
                'sku'             => $itemData->sku ?? SkuGenerator::generate($productName),
                'unit_group_id'   => $itemData->unitGroupId,
                'tax_profile_id'  => $itemData->taxProfileId,
                'is_stockable'    => CatalogItemType::ProductVariant->isStockable(),
                'is_active'       => $itemData->isActive,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            $inventoryConfigurations->put($id, $itemData->inventory);
        }

        CatalogItem::insert($catalogItemRows->all());

        /** @var EloquentCollection<int, CatalogItem> $catalogItems */
        $catalogItems = CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $catalogItemRows->pluck('id')->all())
            ->get()
            ->keyBy('id');

        if (CatalogItemType::ProductVariant->isStockable()) {
            $this->inventoryService->createManyItems($catalogItems->all(), $inventoryConfigurations);
        }

        /** @var EloquentCollection<int, CatalogItem> $catalogItemsInInputOrder */
        $catalogItemsInInputOrder = new EloquentCollection($catalogItemRows->map(
            fn (array $row): CatalogItem => $catalogItems->get($row['id']),
        )->all());

        return $catalogItemsInInputOrder;
    }

    /**
     * @param  Collection<int, CatalogItemData>  $itemsData
     */
    private function assertTaxProfilesAssignable(Collection $itemsData): void
    {
        $itemsData
            ->filter(fn (CatalogItemData $itemData): bool => $itemData->taxProfileId !== null)
            ->groupBy('taxProfileId')
            ->each(fn (Collection $group, string $taxProfileId) => $this->taxService->assertProfileAssignable(
                $taxProfileId,
                $group->pluck('unitGroupId')->unique()->values(),
            ));
    }

    /**
     * Update the catalog item and its inventory representation.
     *
     * The caller owns the surrounding transaction.
     */
    public function update(
        CatalogItem $catalogItem,
        CatalogItemUpdateData $data,
    ): CatalogItem {
        if (is_string($data->taxProfileId)) {
            $this->taxService->assertProfileAssignable($data->taxProfileId, collect([$catalogItem->unit_group_id]));
        }

        $catalogItem->fill(withoutMissing([
            'sku'            => $data->sku,
            'tax_profile_id' => $data->taxProfileId,
            'is_active'      => $data->isActive,
        ]));
        $catalogItem->save();

        if ($catalogItem->item_type->isStockable()) {
            $inventoryData = $data->inventory instanceof MissingValue
                ? []
                : $data->inventory->toArray();

            $this->inventoryService->updateItem($catalogItem, [
                'sku' => $catalogItem->sku,
                ...$inventoryData,
            ]);
        }

        return $catalogItem;
    }

    /**
     * Delete a catalog item and its inventory representation.
     *
     * The caller must delete records referencing the catalog item first.
     * The caller owns the surrounding transaction.
     */
    public function delete(CatalogItem $catalogItem): void
    {
        $usedByBundle = BundleItem::query()
            ->where('organization_id', $catalogItem->organization_id)
            ->where('item_id', $catalogItem->id)
            ->exists();

        if ($usedByBundle) {
            throw CatalogItemException::usedByBundle($catalogItem);
        }

        if ($catalogItem->item_type->isStockable()) {
            $this->inventoryService->deleteItem($catalogItem);
        }
        $catalogItem->delete();
    }
}
