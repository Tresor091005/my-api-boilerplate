<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Master\Enums\NoteVisibility;
use Lahatre\Master\Models\Note;
use Lahatre\Master\Models\NoteMention;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<NoteMention> */
class NoteMentionFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(...),
            'note_id'         => static fn (array $attributes): string => (string) Note::factory()
                ->create([
                    'organization_id' => $attributes['organization_id'],
                    'visibility'      => NoteVisibility::Mentioned,
                ])
                ->getKey(),
            'member_id' => static fn (array $attributes): string => (string) OrganizationMember::factory()
                ->create(['organization_id' => $attributes['organization_id']])
                ->getKey(),
            'mentioned_at' => now(),
            'read_at'      => null,
        ];
    }
}
