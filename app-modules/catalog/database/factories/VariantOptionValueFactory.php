<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<VariantOptionValue>
 */
class VariantOptionValueFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id' => $organizationId,
            'product_id'      => Product::factory(['organization_id' => $organizationId]),
            'variant_id'      => ProductVariant::factory(['organization_id' => $organizationId]),
            'option_id'       => Option::factory(['organization_id' => $organizationId]),
            'option_value_id' => OptionValue::factory(['organization_id' => $organizationId]),
        ];
    }

    /** A link without a position comes after the variant's other option values. */
    public function configure(): static
    {
        return $this->afterMaking(function (VariantOptionValue $link): void {
            $link->position ??= (int) VariantOptionValue::query()
                ->where('organization_id', $link->organization_id)
                ->where('variant_id', $link->variant_id)
                ->max('position') + 1;
        });
    }
}
