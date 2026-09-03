<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Lahatre\Catalog\Data\BundleData;
use Lahatre\Catalog\Data\BundleItemData;
use Lahatre\Catalog\Data\BundleItemQuantityData;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\BundleService;

final class BundleSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = currentOrganizationId();
        $bundleService = app(BundleService::class);

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
                $bundleService,
                $organizationId,
                $handle,
                $name,
                $variants,
            );
        }
    }

    /** @param Collection<string, array{variant: ProductVariant, quantity: int}> $variants */
    private function seedBundle(
        BundleService $bundleService,
        string $organizationId,
        string $handle,
        string $name,
        Collection $variants,
    ): void {
        $bundle = Bundle::query()
            ->where('organization_id', $organizationId)
            ->where('handle', $handle)
            ->first();

        $items = $variants->map(
            fn (array $item): array => $this->bundleItemPayload($item),
        )->values()->all();

        if (!$bundle instanceof Bundle) {
            $bundleService->create(BundleData::fromArray([
                'name'      => $name,
                'is_active' => true,
                'items'     => $items,
                'inventory' => [
                    'stock_tracking_enabled' => true,
                    'is_expirable'           => false,
                ],
            ]));

            return;
        }

        $bundleService->update(
            $bundle,
            BundleData::fromArray([
                'name'      => $name,
                'is_active' => true,
            ], missingFields: ['sku', 'items', 'inventory']),
        );

        $desiredItems = $variants->mapWithKeys(
            fn (array $item): array => [$item['variant']->id => $this->bundleItemPayload($item)],
        );
        /** @var Collection<string, BundleItem> $existingItems */
        $existingItems = $bundle->items()->get()->keyBy('item_id');

        foreach ($desiredItems as $itemId => $payload) {
            $existingItem = $existingItems->get($itemId);

            if ($existingItem === null) {
                $bundleService->addItems($bundle, collect([BundleItemData::fromArray($payload)]));

                continue;
            }

            if ($existingItem->quantity !== $payload['quantity'] || $existingItem->display_unit_code !== $payload['unit_code']) {
                $bundleService->updateItem(
                    $bundle,
                    $existingItem,
                    BundleItemQuantityData::fromArray([
                        'quantity'  => $payload['quantity'],
                        'unit_code' => $payload['unit_code'],
                    ]),
                );
            }
        }

        $staleItemIds = $existingItems->keys()->diff($desiredItems->keys())->values()->all();
        if ($staleItemIds !== []) {
            $bundleService->removeItems($bundle, $staleItemIds);
        }
    }

    /** @param array{variant: ProductVariant, quantity: int} $item */
    private function bundleItemPayload(array $item): array
    {
        $baseUnit = $item['variant']->catalogItem->unitGroup->baseUnit;

        return [
            'item_type' => 'catalog_product_variant',
            'item_id'   => $item['variant']->id,
            'quantity'  => $item['quantity'],
            'unit_code' => $baseUnit->code,
        ];
    }
}
