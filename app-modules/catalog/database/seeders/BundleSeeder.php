<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Shared\Support\SkuGenerator;

final class BundleSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = currentOrganizationId();
        $bundleUnit = Unit::query()
            ->whereNull('organization_id')
            ->where('code', 'bundle')
            ->whereHas('group', fn ($query) => $query
                ->whereNull('organization_id')
                ->where('is_builtin', true))
            ->with('group')
            ->first();

        if (!$bundleUnit instanceof Unit) {
            return;
        }

        $variantSkus = [
            'IP15P-BLA-128',
            'USB-C-HUB-SIL',
            'MBP16-SG-16-512',
            'SGS24-WHI-256',
            'WDT-OAK',
        ];

        $variantsBySku = ProductVariant::query()
            ->where('catalog_product_variants.organization_id', $organizationId)
            ->whereHas('catalogItem', fn ($query) => $query->whereIn('sku', $variantSkus))
            ->with(['catalogItem.unitGroup.baseUnit'])
            ->get()
            ->keyBy(fn (ProductVariant $variant): string => $variant->catalogItem->sku);

        $definitions = [
            ['iphone-starter-pack', 'iPhone Starter Pack', ['IP15P-BLA-128' => 1, 'USB-C-HUB-SIL' => 1]],
            ['laptop-hub-combo', 'Laptop & Hub Combo', ['MBP16-SG-16-512'       => 1, 'USB-C-HUB-SIL' => 2]],
            ['smartphone-power-pack', 'Smartphone Power Pack', ['SGS24-WHI-256' => 1, 'USB-C-HUB-SIL' => 3]],
            ['home-office-furniture', 'Home Office Furniture', ['WDT-OAK'       => 1, 'USB-C-HUB-SIL' => 1]],
        ];

        foreach ($definitions as [$handle, $name, $quantitiesBySku]) {
            $variants = collect($quantitiesBySku)
                ->mapWithKeys(fn (int $quantity, string $sku): array => [$sku => [
                    'variant'  => $variantsBySku->get($sku),
                    'quantity' => $quantity,
                ]]);

            if ($variants->contains(fn (array $item): bool => !$item['variant'] instanceof ProductVariant)) {
                continue;
            }

            $this->seedBundle(
                $organizationId,
                $bundleUnit->group,
                $handle,
                $name,
                $variants,
            );
        }
    }

    /** @param Collection<string, array{variant: ProductVariant, quantity: int}> $variants */
    private function seedBundle(
        string $organizationId,
        UnitGroup $bundleUnitGroup,
        string $handle,
        string $name,
        Collection $variants,
    ): void {
        $bundle = Bundle::query()
            ->where('organization_id', $organizationId)
            ->where('handle', $handle)
            ->first();

        if (!$bundle instanceof Bundle) {
            $catalogItem = new CatalogItem;
            $catalogItem->forceFill([
                'id'              => (string) Str::uuid7(),
                'organization_id' => $organizationId,
                'item_type'       => CatalogItemType::Bundle,
                'sku'             => SkuGenerator::generate($name),
                'unit_group_id'   => $bundleUnitGroup->id,
                'is_stockable'    => CatalogItemType::Bundle->isStockable(),
                'is_active'       => true,
            ])->save();

            $bundle = new Bundle;
            $bundle->forceFill([
                'id'              => $catalogItem->id,
                'organization_id' => $organizationId,
                'handle'          => $handle,
                'name'            => $name,
            ])->save();
        }

        foreach ($variants as $item) {
            $variant = $item['variant'];
            $baseUnit = $variant->catalogItem->unitGroup->baseUnit;

            if ($baseUnit === null) {
                continue;
            }

            $bundle->items()->updateOrCreate(
                ['item_id' => $variant->id],
                [
                    'organization_id'   => $organizationId,
                    'item_type'         => CatalogItemType::ProductVariant->value,
                    'quantity'          => $item['quantity'],
                    'display_unit_code' => $baseUnit->code,
                ],
            );
        }
    }
}
