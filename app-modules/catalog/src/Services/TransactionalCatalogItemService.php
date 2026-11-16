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
use Lahatre\Catalog\Exceptions\CatalogItemPriceException;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Data\InventoryItemConfigurationData;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\SkuGenerator;
use Lahatre\Tax\Contracts\TaxInterface;
use Lahatre\Tax\Data\IncludedTaxAmountData;
use Lahatre\Tax\Exceptions\TaxException;

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
        /** @var Collection<string, Collection<int, string>> $unitGroupIdsByProfile */
        $unitGroupIdsByProfile = $itemsData
            ->filter(fn (CatalogItemData $itemData): bool => $itemData->taxProfileId !== null)
            ->groupBy('taxProfileId')
            ->map(fn (Collection $group): Collection => $group->pluck('unitGroupId')->unique()->values());

        $this->taxService->assertProfilesAssignable($unitGroupIdsByProfile);
    }

    /**
     * Update the catalog item and its inventory representation. A new tax
     * profile must suit the prices of the item: the taxes they already contain
     * belong to it and can still be taken out of them.
     *
     * The caller owns the surrounding transaction.
     *
     * @throws CatalogItemPriceException If the tax profile is removed while prices of the item contain taxes.
     * @throws TaxException If the new tax profile cannot be assigned, or does not suit the taxes the prices contain.
     */
    public function update(
        CatalogItem $catalogItem,
        CatalogItemUpdateData $data,
    ): CatalogItem {
        if (!$data->taxProfileId instanceof MissingValue) {
            $this->assertTaxProfileSuits($catalogItem, $data->taxProfileId);
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
     * Checks the profile an item is about to take, or lose: it can be assigned
     * to the item's unit group, and it suits the prices of the item that
     * contain taxes. The item row is locked first, as every write of its prices
     * locks it, so no price changes meanwhile. The tax module reads the profile
     * once for both checks.
     *
     * @throws CatalogItemPriceException
     * @throws TaxException
     */
    private function assertTaxProfileSuits(CatalogItem $catalogItem, ?string $taxProfileId): void
    {
        $currentTaxProfileId = CatalogItem::query()
            ->where('organization_id', $catalogItem->organization_id)
            ->whereKey($catalogItem->id)
            ->lockForUpdate()
            ->value('tax_profile_id');

        // an unchanged profile keeps suiting the prices: they were checked when written
        $pricesWithTaxes = $currentTaxProfileId === $taxProfileId
            ? new EloquentCollection
            : CatalogItemPrice::query()
                ->where('organization_id', $catalogItem->organization_id)
                ->where('catalog_item_id', $catalogItem->id)
                ->whereHas('includedTaxes')
                ->with(['includedTaxes:id', 'unit:id,code'])
                ->get();

        if ($taxProfileId === null) {
            if ($pricesWithTaxes->isNotEmpty()) {
                throw CatalogItemPriceException::includedTaxesNeedTaxProfile($catalogItem);
            }

            return;
        }

        $this->taxService->assertProfileAssignableWithIncludedTaxes(
            $taxProfileId,
            collect([$catalogItem->unit_group_id]),
            $pricesWithTaxes->map(fn (CatalogItemPrice $price): IncludedTaxAmountData => IncludedTaxAmountData::fromArray([
                'amount'           => (string) $price->amount,
                'included_tax_ids' => $price->includedTaxes->pluck('id')->all(),
                'unit_code'        => $price->unit->code,
            ]))->values(),
        );
    }

    /**
     * Delete a catalog item, its prices and its inventory representation.
     *
     * The caller must delete records referencing the catalog item first.
     * The caller owns the surrounding transaction.
     *
     * @throws CatalogItemException If a bundle uses the item.
     */
    public function delete(CatalogItem $catalogItem): void
    {
        $this->deleteMany(new EloquentCollection([$catalogItem]));
    }

    /**
     * Delete catalog items of the current organization, their prices and
     * their inventory representations, all or none, with a fixed number of
     * queries whatever their count.
     *
     * The caller must delete records referencing the catalog items first.
     * The caller owns the surrounding transaction.
     *
     * @param  EloquentCollection<int, CatalogItem>  $catalogItems
     *
     * @throws CatalogItemException If a bundle uses one of the items.
     */
    public function deleteMany(EloquentCollection $catalogItems): void
    {
        if ($catalogItems->isEmpty()) {
            return;
        }

        $organizationId = currentOrganizationId();
        $catalogItemIds = $catalogItems->pluck('id')->values();
        $usedCatalogItemId = BundleItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('item_id', $catalogItemIds)
            ->orderBy('item_id')
            ->value('item_id');

        if ($usedCatalogItemId !== null) {
            throw CatalogItemException::usedByBundle($catalogItems->firstOrFail('id', $usedCatalogItemId));
        }

        $this->inventoryService->deleteManyItems(
            $catalogItems->filter(fn (CatalogItem $catalogItem): bool => $catalogItem->item_type->isStockable())->values()->all(),
        );
        CatalogItemPrice::query()
            ->where('organization_id', $organizationId)
            ->whereIn('catalog_item_id', $catalogItemIds)
            ->delete();
        CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $catalogItemIds)
            ->delete();
    }
}
