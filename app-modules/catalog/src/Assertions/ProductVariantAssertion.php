<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Illuminate\Support\Collection;
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

    /**
     * Assert that each combination of option values is used by one variant of
     * the product only, among the given ones and the product's other live
     * variants.
     *
     * @param  Collection<int, array{signature: string, options: array<int, array{name: string, value: string}>}>  $combinations
     * @param  Collection<int, string>  $takenSignatures  signatures of the product's other live variants among the given ones
     *
     * @throws ProductVariantException If two given variants share a combination or one is already taken.
     */
    public function assertOptionCombinationsAvailable(Product $product, Collection $combinations, Collection $takenSignatures): void
    {
        $repeated = $combinations->pluck('signature')->duplicates()->merge($takenSignatures)->unique();

        if ($repeated->isEmpty()) {
            return;
        }

        throw ProductVariantException::optionCombinationTaken(
            $product,
            $combinations->whereIn('signature', $repeated)->unique('signature')->pluck('options')->values()->all(),
        );
    }
}
