<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Variant;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\CatalogItemData;
use Lahatre\Catalog\Data\ProductVariantData;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Catalog\Services\TransactionalCatalogItemService;

class TransactionalProductVariantService
{
    public function __construct(
        protected TransactionalCatalogItemService $transactionalCatalogItemService,
        protected TransactionalOptionService $transactionalOptionService
    ) {}

    /**
     * @param  Collection<int, ProductVariantData>  $variantsData
     * @return EloquentCollection<int, ProductVariant>
     */
    public function createMany(Product $product, Collection $variantsData): EloquentCollection
    {
        if ($variantsData->isEmpty()) {
            return new EloquentCollection;
        }

        $catalogItems = $this->transactionalCatalogItemService->createManyProductVariants(
            $product->organization_id,
            $product->name,
            $variantsData->map(
                fn (ProductVariantData $variantData): CatalogItemData => $variantData->catalogItem(),
            ),
        );

        $variantRows = collect();
        $variantOptions = collect();
        $variantDataWithCatalogItems = $variantsData->values()->zip($catalogItems->values());

        foreach ($variantDataWithCatalogItems as $pair) {
            /** @var ProductVariantData $variantData */
            $variantData = $pair[0];
            /** @var CatalogItem $catalogItem */
            $catalogItem = $pair[1];

            $variantRows->push([
                'id'              => $catalogItem->id,
                'organization_id' => $product->organization_id,
                'product_id'      => $product->id,
                'created_at'      => $catalogItem->created_at,
                'updated_at'      => $catalogItem->updated_at,
            ]);
            $variantOptions->put($catalogItem->id, $variantData->options);
        }

        ProductVariant::insert($variantRows->all());

        /** @var EloquentCollection<int, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->where('organization_id', $product->organization_id)
            ->whereIn('id', $variantRows->pluck('id')->all())
            ->get();

        $this->attachOptions($product, $variantOptions);

        $variantsById = $variants->keyBy('id');
        foreach ($variantDataWithCatalogItems as $pair) {
            /** @var ProductVariantData $variantData */
            $variantData = $pair[0];
            /** @var CatalogItem $catalogItem */
            $catalogItem = $pair[1];

            if ($variantData->labels === []) {
                continue;
            }

            $variantsById->get($catalogItem->id)?->attachLabels($variantData->labels);
        }

        return $variants;
    }

    /**
     * @param  array<int, array{name: string, value: string}>  $optionsData
     */
    public function replaceOptions(Product $product, ProductVariant $variant, array $optionsData): void
    {
        VariantOptionValue::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->delete();

        $this->attachOptions($product, collect([
            $variant->id => $optionsData,
        ]));
    }

    public function delete(ProductVariant $variant): void
    {
        VariantOptionValue::query()
            ->where('organization_id', $variant->organization_id)
            ->where('product_id', $variant->product_id)
            ->where('variant_id', $variant->id)
            ->delete();

        /** @var CatalogItem $catalogItem */
        $catalogItem = $variant->catalogItem()->firstOrFail();

        $variant->delete();
        $this->transactionalCatalogItemService->delete($catalogItem);
    }

    /**
     * @param  Collection<string, array<int, array{name: string, value: string}>>  $variantOptions
     */
    protected function attachOptions(Product $product, Collection $variantOptions): void
    {
        $allOptions = $variantOptions->flatMap(fn (array $options): array => $options);

        if ($allOptions->isEmpty()) {
            return;
        }

        $optionValuesMap = $this->transactionalOptionService->resolveOrCreateValues($allOptions);

        $pivotRows = [];
        foreach ($variantOptions as $variantId => $optionsData) {
            foreach ($optionsData as $optionData) {
                $mapKey = $optionData['name'].'-'.$optionData['value'];
                $optionValue = $optionValuesMap->get($mapKey);

                if (!$optionValue) {
                    continue;
                }

                $pivotRows[] = [
                    'id'              => (string) Str::uuid7(),
                    'organization_id' => $product->organization_id,
                    'product_id'      => $product->id,
                    'variant_id'      => $variantId,
                    'option_id'       => $optionValue->option_id,
                    'option_value_id' => $optionValue->id,
                ];
            }
        }

        if ($pivotRows !== []) {
            VariantOptionValue::insert($pivotRows);
        }
    }
}
