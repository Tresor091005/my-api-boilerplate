<?php

declare(strict_types=1);

namespace Lahatre\Sale\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Master\Models\Unit;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * A line of one kg at 1000, without tax.
 *
 * @extends Factory<SaleLine>
 */
class SaleLineFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();
        $unit = Unit::query()->whereNull('organization_id')->where('code', 'kg')->firstOrFail();

        return [
            'organization_id'     => $organizationId,
            'sale_id'             => fn (array $attributes): string => Sale::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'position'            => 0,
            'catalog_item_id'     => fn (array $attributes): string => CatalogItem::factory()->create(['organization_id' => $attributes['organization_id'], 'unit_group_id' => $unit->group_id])->id,
            'quantity'            => $unit->ratio,
            'item_type'           => CatalogItemType::ProductVariant,
            'item_name'           => fake()->words(2, true),
            'item_sku'            => fake()->unique()->bothify('SKU-####'),
            'display_unit_code'   => $unit->code,
            'unit_name'           => $unit->name,
            'price_level_id'      => fn (array $attributes): string => PriceLevel::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'price_level_name'    => fn (array $attributes): string => (string) PriceLevel::query()->whereKey($attributes['price_level_id'])->value('name'),
            'unit_amount'         => 1000,
            'tax_profile_id'      => null,
            'amount'              => 1000,
            'base_amount'         => 1000,
            'included_tax_amount' => 0,
            'added_tax_amount'    => 0,
            'total_amount'        => 1000,
            'tax_result'          => [],
        ];
    }
}
