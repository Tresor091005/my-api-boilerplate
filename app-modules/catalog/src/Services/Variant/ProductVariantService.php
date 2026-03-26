<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Variant;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\DTO\ProductVariantDataDTO;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Catalog\Services\Option\OptionService;
use Lahatre\Shared\Contracts\Services\TransactionalService;
use Lahatre\Shared\Support\SkuGenerator;

class ProductVariantService implements TransactionalService
{
    public function __construct(
        protected OptionService $optionService
    ) {}

    /**
     * @param  Collection<int, ProductVariantDataDTO>  $variantsData
     * @return array<int, string>
     */
    public function add(Product $product, Collection $variantsData): array
    {
        if ($variantsData->isEmpty()) {
            return [];
        }

        $now = now();

        $variantRows = $variantsData->map(fn (ProductVariantDataDTO $variantDto): array => [
            'id'                  => (string) Str::uuid7(),
            'product_id'          => $product->id,
            'sku'                 => $variantDto->sku ?? SkuGenerator::generate($product->name),
            'unit_group_id'       => $variantDto->unit_group_id,
            'should_manage_stock' => $variantDto->should_manage_stock,
            'is_active'           => $variantDto->is_active,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        ProductVariant::insert($variantRows->all());

        $allOptionsFromVariants = $variantsData->flatMap(fn (ProductVariantDataDTO $v): array => $v->options ?? []);
        $optionValuesMap = $this->optionService->getOrCreate($allOptionsFromVariants);

        $pivotRows = [];
        foreach ($variantsData as $index => $variantDto) {
            if (empty($variantDto->options)) {
                continue;
            }

            $variantId = $variantRows[$index]['id'];

            foreach ($variantDto->options as $optionData) {
                $mapKey = $optionData['name'].'-'.$optionData['value'];

                $optionValue = $optionValuesMap->get($mapKey);

                if ($optionValue) {
                    $pivotRows[] = [
                        'id'              => (string) Str::uuid7(),
                        'product_id'      => $product->id,
                        'variant_id'      => $variantId,
                        'option_id'       => $optionValue->option_id,
                        'option_value_id' => $optionValue->id,
                    ];
                }
            }
        }

        if ($pivotRows !== []) {
            VariantOptionValue::insert($pivotRows);
        }

        return $variantRows->pluck('id')->all();
    }
}
