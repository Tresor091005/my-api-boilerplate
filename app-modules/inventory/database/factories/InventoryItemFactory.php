<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id'    => $organizationId,
            'itemable_type'      => 'test_material',
            'itemable_id'        => (string) Str::uuid7(),
            'sku'                => fake()->unique()->bothify('SKU-####-????'),
            'base_unit_code'     => 'unit',
            'deduction_strategy' => null,
            'is_expirable'       => false,
            'is_active'          => true,
        ];
    }
}
