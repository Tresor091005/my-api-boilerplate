<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\ProductVariant\ProductVariantIsLastException;
use Lahatre\Catalog\Models\ProductVariant;

class ProductVariantAssertion
{
    public function assertCanDelete(ProductVariant $variant): void
    {
        $lastVariant = ProductVariant::where('product_id', $variant->product_id)
            ->where('id', '!=', $variant->id)
            ->exists();

        if ($lastVariant) {
            throw new ProductVariantIsLastException($variant);
        }
    }
}
