<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\Product;
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

    public static function notAttachedToProduct(Product $product, ProductVariant $variant): self
    {
        return new self(
            __('catalog::exceptions.product_variant_not_attached_to_product'),
            [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
            ]
        );
    }

    /** @param list<array<int, array{name: string, value: string}>> $combinations */
    public static function optionCombinationTaken(Product $product, array $combinations): self
    {
        return new self(
            __('catalog::exceptions.product_variant_option_combination_taken'),
            [
                'product_id' => $product->id,
                'options'    => $combinations,
            ]
        );
    }

    /** @param list<array{name: string, value: string}> $options */
    public static function optionsUnavailable(Product $product, array $options): self
    {
        return new self(
            __('catalog::exceptions.product_variant_options_unavailable'),
            [
                'product_id' => $product->id,
                'options'    => $options,
            ]
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
