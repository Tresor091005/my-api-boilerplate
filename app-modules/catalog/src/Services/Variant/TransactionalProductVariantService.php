<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Variant;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\CatalogItemData;
use Lahatre\Catalog\Data\ProductVariantData;
use Lahatre\Catalog\Enums\CatalogItemType;
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
        $this->deleteMany($variant->product_id, new EloquentCollection([$variant]));
    }

    /**
     * Delete variants of one product of the current organization with their
     * option values, catalog items, prices and inventory items, all or none,
     * with a fixed number of queries whatever their count.
     *
     * The caller owns the surrounding transaction.
     *
     * @param  EloquentCollection<int, ProductVariant>  $variants  variants of that product
     *
     * @throws ModelNotFoundException If a variant has no catalog item.
     */
    public function deleteMany(string $productId, EloquentCollection $variants): void
    {
        if ($variants->isEmpty()) {
            return;
        }

        $organizationId = currentOrganizationId();
        $variantIds = $variants->pluck('id')->unique()->values();

        VariantOptionValue::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->whereIn('variant_id', $variantIds)
            ->delete();

        // a variant and its catalog item share their id
        $catalogItems = CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->where('item_type', CatalogItemType::ProductVariant->value)
            ->whereIn('id', $variantIds)
            ->get();
        $missingIds = $variantIds->diff($catalogItems->pluck('id'))->values()->all();

        if ($missingIds !== []) {
            throw new ModelNotFoundException()->setModel(CatalogItem::class, $missingIds);
        }

        ProductVariant::query()
            ->where('organization_id', $organizationId)
            ->where('product_id', $productId)
            ->whereIn('id', $variantIds)
            ->delete();
        $this->transactionalCatalogItemService->deleteMany($catalogItems);
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
