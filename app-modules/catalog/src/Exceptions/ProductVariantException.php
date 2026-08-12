<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Shared\Exceptions\AssertionException;

final class ProductVariantException extends AssertionException
{
    public static function isLast(ProductVariant $variant): self
    {
        return new self(
            __('catalog::exceptions.product_variant_is_last'),
            [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
            ]
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
