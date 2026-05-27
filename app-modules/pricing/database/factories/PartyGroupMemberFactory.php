<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Pricing\Models\PartyGroup;
use Lahatre\Pricing\Models\PartyGroupMember;

/**
 * @extends Factory<PartyGroupMember>
 */
class PartyGroupMemberFactory extends Factory
{
    public function definition(): array
    {
        $group = PartyGroup::factory()->create();

        // TODO: group is member of himself - is that a problem ?
        return [
            'organization_id' => $group->organization_id,
            'group_id'        => $group->id,
            'party_type'      => $group->getMorphClass(),
            'party_id'        => $group->id,
        ];
    }
}
