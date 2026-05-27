<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Pricing\Models\PriceableGroup;
use Lahatre\Pricing\Models\PriceEntry;

/**
 * @extends Factory<PriceEntry>
 */
class PriceEntryFactory extends Factory
{
    public function definition(): array
    {
        $group = PriceableGroup::factory()->create();
        $unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
        $unit = Unit::factory()->create([
            'organization_id' => null,
            'code'            => fake()->unique()->lexify('u??'),
            'ratio'           => 1,
            'group_id'        => $unitGroup->id,
        ]);
        $currency = Currency::factory()->create([
            'code' => fake()->unique()->lexify('C??'),
        ]);

        return [
            'organization_id' => $group->organization_id,
            'priceable_type'  => $group->getMorphClass(),
            'priceable_id'    => $group->id,
            'priceable_kind'  => 'group',
            'party_type'      => null,
            'party_id'        => null,
            'party_kind'      => null,
            'context'         => 'selling',
            'currency_code'   => $currency->code,
            'unit_code'       => $unit->code,
            'min_quantity'    => 0,
            'max_quantity'    => null,
            'unit_price'      => 1000,
            'starts_at'       => null,
            'ends_at'         => null,
            'is_active'       => true,
            'metadata'        => null,
        ];
    }
}
