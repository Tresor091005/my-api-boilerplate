<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Lahatre\Catalog\Data\BundleItemData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Exceptions\BundleException;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Master\Models\Unit;

final class BundleAssertion
{
    /**
     * Assert that a new bundle starts with the minimum number of components.
     *
     * The bundle may be created when at least two component items are supplied.
     *
     * @param  Collection<int, BundleItemData>  $itemsData
     *
     * @throws BundleException If fewer than two component items are supplied.
     */
    public function assertCanCreate(Collection $itemsData): void
    {
        if ($itemsData->count() < 2) {
            throw BundleException::requiresTwoItems();
        }
    }

    /**
     * Assert that the supplied components can be attached to the same bundle.
     *
     * The components may be attached when every item is unique, available in
     * the current organization, uses an allowed component type, has a positive
     * quantity, and references a display unit from the item's unit group.
     *
     * @param  Collection<int, BundleItemData>  $itemsData
     * @param  EloquentCollection<string, CatalogItem>  $catalogItems
     * @param  EloquentCollection<string, Unit>  $units
     * @param  Collection<int, string>  $existingItemIds
     *
     * @throws BundleException If a component is duplicated or unavailable.
     * @throws BundleException If a component has a non-positive quantity or a disallowed type.
     * @throws BundleException If a component uses a display unit from another unit group.
     */
    public function assertItemsCanBeAttached(
        Collection $itemsData,
        EloquentCollection $catalogItems,
        EloquentCollection $units,
        Collection $existingItemIds,
    ): void {
        $duplicateItemId = $itemsData
            ->pluck('itemId')
            ->duplicates()
            ->first();

        if ($duplicateItemId !== null) {
            throw BundleException::duplicateItem((string) $duplicateItemId);
        }

        $requestedIds = $itemsData->pluck('itemId')->values();
        $missingIds = $requestedIds
            ->reject(fn (string $itemId): bool => $catalogItems->has($itemId))
            ->values();

        if ($missingIds->isNotEmpty()) {
            throw BundleException::itemsUnavailable($missingIds->all());
        }

        foreach ($itemsData as $itemData) {
            $this->assertQuantityIsPositive($itemData->quantity);

            if ($existingItemIds->contains($itemData->itemId)) {
                throw BundleException::duplicateItem($itemData->itemId);
            }

            $catalogItem = $catalogItems->get($itemData->itemId);
            if (!$catalogItem instanceof CatalogItem || !$catalogItem->is_active) {
                throw BundleException::itemsUnavailable([$itemData->itemId]);
            }

            if ($catalogItem->item_type !== $itemData->itemType
                || !in_array($itemData->itemType, CatalogItemType::allowedBundleComponentTypes(), true)) {
                throw BundleException::itemTypeNotAllowed($itemData->itemId, $itemData->itemType->value);
            }

            $this->assertUnitCanRepresentItem($catalogItem, $units->get($itemData->unitCode), $itemData->unitCode);
        }
    }

    /**
     * Assert that removing components preserves a valid bundle composition.
     *
     * The components may be removed when at least two components remain.
     *
     * @throws BundleException If fewer than two components would remain.
     */
    public function assertCanRemoveItems(Bundle $bundle, int $remainingItems): void
    {
        if ($remainingItems < 2) {
            throw BundleException::requiresTwoItems($bundle);
        }
    }

    /**
     * Assert that a bundle component quantity is strictly positive.
     *
     * The quantity may be persisted when it is greater than zero.
     *
     * @throws BundleException If the quantity is zero or negative.
     */
    public function assertQuantityIsPositive(int $quantity): void
    {
        if ($quantity < 1) {
            throw BundleException::quantityMustBePositive();
        }
    }

    /**
     * Assert that a component belongs to the supplied bundle and organization.
     *
     * The component may be changed when its bundle and organization identifiers
     * match those of the supplied bundle.
     *
     * @throws BundleException If the component belongs to another bundle or organization.
     */
    public function assertItemBelongsToBundle(Bundle $bundle, BundleItem $bundleItem): void
    {
        if ($bundleItem->bundle_id !== $bundle->id
            || $bundleItem->organization_id !== $bundle->organization_id) {
            throw BundleException::itemsUnavailable([$bundleItem->id]);
        }
    }

    /**
     * Assert that a selected unit belongs to the component item's unit group.
     *
     * The unit may be used for the component quantity when it is present and
     * belongs to the same unit group as the catalog item.
     *
     * @throws BundleException If the unit is unavailable or belongs to another unit group.
     */
    public function assertUnitCanRepresentItem(CatalogItem $catalogItem, ?Unit $unit, string $unitCode): void
    {
        if (!$unit instanceof Unit || $unit->group_id !== $catalogItem->unit_group_id) {
            throw BundleException::itemUnitMismatch($catalogItem->id, $unitCode);
        }
    }
}
