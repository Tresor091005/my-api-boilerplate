<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Master\Enums\ContactType;
use Lahatre\Master\Models\Contact;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'type'            => ContactType::Email,
            'value'           => fake()->safeEmail(),
            'is_primary'      => false,
        ];
    }
}
