<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Variant;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\ProductVariantData;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Shared\Support\SkuGenerator;

class TransactionalProductVariantService
{
    public function __construct(
        protected InventoryInterface $inventoryService,
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

        $now = now();

        $variantRows = $variantsData->map(fn (ProductVariantData $variantData): array => [
            'id'              => (string) Str::uuid7(),
            'organization_id' => $product->organization_id,
            'product_id'      => $product->id,
            'sku'             => $variantData->sku ?? SkuGenerator::generate($product->name),
            'unit_group_id'   => $variantData->unitGroupId,
            'is_active'       => $variantData->isActive,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        ProductVariant::insert($variantRows->all());

        /** @var EloquentCollection<int, ProductVariant> $variants */
        $variants = ProductVariant::whereIn('id', $variantRows->pluck('id')->all())->get();
        $inventoryConfigurations = $variantsData->mapWithKeys(
            fn (ProductVariantData $variantData, int $index): array => [
                (string) $variantRows[$index]['id'] => $variantData->inventory,
            ]
        );
        $this->inventoryService->createManyItems($variants->all(), $inventoryConfigurations);

        $this->attachOptions(
            $product,
            $variantsData->mapWithKeys(
                fn (ProductVariantData $variantData, int $index): array => [$variantRows[$index]['id'] => $variantData->options]
            )
        );

        $variantsById = $variants->keyBy('id');
        foreach ($variantsData as $index => $variantData) {
            if ($variantData->labels === []) {
                continue;
            }

            $variantsById->get($variantRows[$index]['id'])?->attachLabels($variantData->labels);
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

        $this->inventoryService->deleteItem($variant);

        $variant->delete();
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
