<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\CatalogItemData;
use Lahatre\Catalog\Data\CatalogItemUpdateData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\SkuGenerator;

class TransactionalCatalogItemService
{
    public function __construct(
        protected InventoryInterface $inventoryService,
    ) {}

    /**
     * @param  Collection<int, CatalogItemData>  $itemsData
     * @return EloquentCollection<int, CatalogItem>
     */
    public function createManyProductVariants(
        string $organizationId,
        string $productName,
        Collection $itemsData,
    ): EloquentCollection {
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
     * Update the catalog item and its inventory representation.
     *
     * The caller owns the surrounding transaction.
     */
    public function update(
        CatalogItem $catalogItem,
        CatalogItemUpdateData $data,
    ): CatalogItem {
        $catalogItem->fill(withoutMissing([
            'sku'       => $data->sku,
            'is_active' => $data->isActive,
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
        if ($catalogItem->item_type->isStockable()) {
            $this->inventoryService->deleteItem($catalogItem);
        }
        $catalogItem->delete();
    }
}
