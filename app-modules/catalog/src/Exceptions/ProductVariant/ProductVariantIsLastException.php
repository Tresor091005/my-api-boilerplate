<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions\ProductVariant;

use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Shared\Exceptions\AssertionException;

class ProductVariantIsLastException extends AssertionException
{
    public function __construct(ProductVariant $variant)
    {
        parent::__construct(
            __('catalog::exceptions.product_variant_is_last'),
            [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
            ]
        );
    }
}
