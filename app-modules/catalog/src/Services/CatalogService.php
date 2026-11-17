<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Contracts\CatalogInterface;
use Lahatre\Catalog\Data\SellableItemQueryData;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\ViewData\SellableItemViewData;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Unit;

final readonly class CatalogService implements CatalogInterface
{
    public function __construct(private MasterInterface $masterInterface) {}

    /**
     * One query per lookup however many requests, so the count does not grow.
     *
     * @param  Collection<int, SellableItemQueryData>  $requests
     * @return Collection<string, SellableItemViewData>
     */
    public function sellableItems(string $organizationId, Collection $requests): Collection
    {
        $itemIds = $requests->pluck('catalogItemId')->unique()->filter(fn (string $id): bool => Str::isUuid($id))->values();

        if ($itemIds->isEmpty()) {
            return collect();
        }

        $items = CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $itemIds)
            ->get(['id', 'item_type', 'sku', 'unit_group_id', 'tax_profile_id', 'is_active'])
            ->keyBy('id');
        $names = $this->namesOf($items, $organizationId);
        $units = $this->masterInterface->units($requests->pluck('unitCode')->unique()->values());
        $priceLevels = $this->priceLevelsOf($requests, $organizationId);
        $prices = $this->pricesOf($requests, $items, $units, $priceLevels, $organizationId);

        return $requests
            ->filter(fn (SellableItemQueryData $request): bool => $items->has($request->catalogItemId))
            ->mapWithKeys(function (SellableItemQueryData $request) use ($items, $names, $units, $priceLevels, $prices): array {
                $item = $items->get($request->catalogItemId);
                $unit = $units->get($request->unitCode);
                $unit = $unit?->group_id === $item->unit_group_id ? $unit : null;
                $priceLevel = $priceLevels->get($request->priceLevelId);
                $price = $unit === null || $priceLevel === null ? null : $prices->get("{$item->id}:{$unit->id}:{$priceLevel->id}");

                return [$request->key() => new SellableItemViewData(
                    catalogItemId: $item->id,
                    itemType: $item->item_type,
                    name: $names->get($item->id),
                    sku: $item->sku,
                    isActive: $item->is_active,
                    unitGroupId: $item->unit_group_id,
                    taxProfileId: $item->tax_profile_id,
                    unitCode: $request->unitCode,
                    unitId: $unit?->id,
                    unitName: $unit?->name,
                    priceLevelId: $request->priceLevelId,
                    priceLevelName: $priceLevel?->name,
                    currencyCode: $priceLevel?->currency_code,
                    unitAmount: $price?->amount,
                    includedTaxIds: $price === null ? [] : $price->includedTaxes->pluck('id')->values()->all(),
                )];
            });
    }

    /**
     * @param  Collection<int, SellableItemQueryData>  $requests
     * @return Collection<string, PriceLevel>
     */
    private function priceLevelsOf(Collection $requests, string $organizationId): Collection
    {
        $priceLevelIds = $requests->pluck('priceLevelId')->unique()->filter(fn (string $id): bool => Str::isUuid($id))->values();

        if ($priceLevelIds->isEmpty()) {
            return collect();
        }

        return PriceLevel::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $priceLevelIds)
            ->get(['id', 'name', 'currency_code'])
            ->keyBy('id');
    }

    /**
     * Reads only the requested (item, unit, level) triples, keyed "{item id}:{unit id}:{level id}".
     *
     * @param  Collection<int, SellableItemQueryData>  $requests
     * @param  Collection<string, CatalogItem>  $items
     * @param  Collection<string, Unit>  $units
     * @param  Collection<string, PriceLevel>  $priceLevels
     * @return Collection<string, CatalogItemPrice>
     */
    private function pricesOf(Collection $requests, Collection $items, Collection $units, Collection $priceLevels, string $organizationId): Collection
    {
        $triples = $requests
            ->map(function (SellableItemQueryData $request) use ($items, $units, $priceLevels): ?array {
                $item = $items->get($request->catalogItemId);
                $unit = $units->get($request->unitCode);

                return $item === null || $unit?->group_id !== $item->unit_group_id || !$priceLevels->has($request->priceLevelId)
                    ? null
                    : [$item->id, $unit->id, $request->priceLevelId];
            })
            ->filter()
            ->unique(fn (array $triple): string => implode(':', $triple))
            ->values();

        if ($triples->isEmpty()) {
            return collect();
        }

        return CatalogItemPrice::query()
            ->where('organization_id', $organizationId)
            ->whereRaw(
                '(catalog_item_id, unit_id, price_level_id) IN ('.$triples->map(fn (): string => '(?, ?, ?)')->implode(', ').')',
                $triples->flatten()->all(),
            )
            ->with('includedTaxes')
            ->get()
            ->keyBy(fn (CatalogItemPrice $price): string => "{$price->catalog_item_id}:{$price->unit_id}:{$price->price_level_id}");
    }

    /**
     * @param  Collection<string, CatalogItem>  $items
     * @return Collection<string, string> names keyed by item id
     */
    private function namesOf(Collection $items, string $organizationId): Collection
    {
        $idsOfType = fn (CatalogItemType $type): Collection => $items
            ->filter(fn (CatalogItem $item): bool => $item->item_type === $type)
            ->keys();

        return $this->variantNames($idsOfType(CatalogItemType::ProductVariant), $organizationId)
            ->union($this->ownNames(Bundle::class, $idsOfType(CatalogItemType::Bundle), $organizationId))
            ->union($this->ownNames(Service::class, $idsOfType(CatalogItemType::Service), $organizationId));
    }

    /**
     * @param  class-string<Bundle|Service>  $model
     * @param  Collection<int, string>  $ids
     * @return Collection<string, string>
     */
    private function ownNames(string $model, Collection $ids, string $organizationId): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return $model::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $ids)
            ->pluck('name', 'id');
    }

    /**
     * Names look like "Shirt (Red, L)", option values in variant order.
     *
     * @param  Collection<int, string>  $variantIds
     * @return Collection<string, string>
     */
    private function variantNames(Collection $variantIds, string $organizationId): Collection
    {
        if ($variantIds->isEmpty()) {
            return collect();
        }

        $variants = ProductVariant::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $variantIds)
            ->get(['id', 'product_id']);
        $productNames = Product::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $variants->pluck('product_id')->unique())
            ->pluck('name', 'id');
        $valuesByVariant = VariantOptionValue::query()
            ->join('catalog_option_values', function (JoinClause $join): void {
                $join->on('catalog_option_values.id', '=', 'catalog_variant_option_value.option_value_id')
                    ->on('catalog_option_values.organization_id', '=', 'catalog_variant_option_value.organization_id');
            })
            ->where('catalog_variant_option_value.organization_id', $organizationId)
            ->whereIn('catalog_variant_option_value.variant_id', $variantIds)
            ->whereNull('catalog_option_values.deleted_at')
            ->orderBy('catalog_variant_option_value.position')
            ->get(['catalog_variant_option_value.variant_id', 'catalog_option_values.value'])
            ->groupBy('variant_id');

        return $variants->mapWithKeys(function (ProductVariant $variant) use ($productNames, $valuesByVariant): array {
            $values = $valuesByVariant->get($variant->id, collect())->pluck('value');
            $name = $productNames->get($variant->product_id, '');

            return [$variant->id => $values->isEmpty() ? $name : "{$name} ({$values->implode(', ')})"];
        });
    }
}
