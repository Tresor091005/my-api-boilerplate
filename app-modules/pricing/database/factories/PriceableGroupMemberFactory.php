<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Pricing\Models\PriceableGroup;
use Lahatre\Pricing\Models\PriceableGroupMember;

/**
 * @extends Factory<PriceableGroupMember>
 */
class PriceableGroupMemberFactory extends Factory
{
    public function definition(): array
    {
        $group = PriceableGroup::factory()->create();

        return [
            'organization_id' => $group->organization_id,
            'group_id'        => $group->id,
            'priceable_type'  => $group->getMorphClass(),
            'priceable_id'    => $group->id,
        ];
    }
}
