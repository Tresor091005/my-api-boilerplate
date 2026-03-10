<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services\Variant;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Catalog\DTO\ProductVariantDataDTO;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Shared\Contracts\Services\TransactionalService;
use Lahatre\Shared\Support\SkuGenerator;

class ProductVariantService implements TransactionalService
{
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

        $rows = $variantsData->map(function (ProductVariantDataDTO $variantDto) use ($product, $now): array {
            return [
                'id'            => (string) Str::uuid7(),
                'product_id'    => $product->id,
                'sku'           => $variantDto->sku ?? SkuGenerator::generate($product->name),
                'unit_group_id' => $variantDto->unit_group_id,
                'manage_stock'  => $variantDto->manage_stock,
                'is_active'     => $variantDto->is_active,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        });

        ProductVariant::insert($rows->all());

        return $rows->pluck('id')->all();
    }
}
