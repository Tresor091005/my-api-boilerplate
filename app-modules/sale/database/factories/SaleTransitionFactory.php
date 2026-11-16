<?php

declare(strict_types=1);

namespace Lahatre\Sale\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Sale\Enums\SaleEvent;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleTransition;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * The creation of a draft, by nobody in particular.
 *
 * @extends Factory<SaleTransition>
 */
class SaleTransitionFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'sale_id'         => fn (array $attributes): string => Sale::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'event'           => SaleEvent::Create,
            'from_status'     => null,
            'to_status'       => SaleStatus::Draft,
            'member_id'       => null,
            'reason'          => null,
        ];
    }
}
