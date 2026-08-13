<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\ProductVariantException;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;

class ProductVariantAssertion
{
    /**
     * Asserts that a product variant belongs to the selected product and organization.
     *
     * @throws ProductVariantException If the variant is attached to another product or organization.
     */
    public function assertBelongsToProduct(Product $product, ProductVariant $variant): void
    {
        if ((string) $variant->product_id !== (string) $product->getKey()
            || (string) $variant->organization_id !== (string) $product->organization_id) {
            throw ProductVariantException::notAttachedToProduct($product, $variant);
        }
    }

    /**
     * Assert that deleting a variant would leave at least one variant on its product.
     *
     * A focused existence query is sufficient because the deleting service only
     * needs to know whether another variant exists.
     *
     * @throws ProductVariantException If this is the product's last variant.
     */
    public function assertCanDelete(ProductVariant $variant): void
    {
        $otherVariantsExist = ProductVariant::where('product_id', $variant->product_id)
            ->where('organization_id', $variant->organization_id)
            ->where('id', '!=', $variant->id)
            ->exists();

        if (!$otherVariantsExist) {
            throw ProductVariantException::isLast($variant);
        }
    }
}
