<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Master\Enums\NoteKind;
use Lahatre\Master\Enums\NoteVisibility;
use Lahatre\Master\Models\Note;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<Note> */
class NoteFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(...),
            'notable_type'    => 'master_unit',
            'notable_id'      => static fn (array $attributes): string => (string) Unit::factory()
                ->create(['organization_id' => $attributes['organization_id']])
                ->getKey(),
            'author_id' => static fn (array $attributes): string => (string) OrganizationMember::factory()
                ->create(['organization_id' => $attributes['organization_id']])
                ->getKey(),
            'parent_id'  => null,
            'position'   => 0,
            'body'       => $this->faker->sentence(),
            'kind'       => NoteKind::Info,
            'visibility' => NoteVisibility::Organization,
            'pinned_at'  => null,
            'expires_at' => null,
            'edited_at'  => null,
        ];
    }
}
