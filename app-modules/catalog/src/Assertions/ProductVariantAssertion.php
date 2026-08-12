<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\ProductVariantException;
use Lahatre\Catalog\Models\ProductVariant;

class ProductVariantAssertion
{
    public function assertCanDelete(ProductVariant $variant): void
    {
        $otherVariantsExist = ProductVariant::where('product_id', $variant->product_id)
            ->where('id', '!=', $variant->id)
            ->exists();

        if (!$otherVariantsExist) {
            throw ProductVariantException::isLast($variant);
        }
    }
}
