<?php

declare(strict_types=1);

namespace Lahatre\Sale\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Sale\Models\Fulfillment;
use Lahatre\Sale\Models\FulfillmentLine;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<FulfillmentLine>
 */
class FulfillmentLineFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'fulfillment_id'  => fn (array $attributes): string => Fulfillment::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'sale_line_id'    => fn (array $attributes): string => SaleLine::factory()->create([
                'organization_id' => $attributes['organization_id'],
                'sale_id'         => Fulfillment::query()->whereKey($attributes['fulfillment_id'])->value('sale_id'),
            ])->id,
            'stock_location_id' => null,
            'quantity'          => fn (array $attributes): int => (int) SaleLine::query()->whereKey($attributes['sale_line_id'])->value('quantity'),
            'stock_ids'         => null,
            'total_cost'        => null,
        ];
    }
}
