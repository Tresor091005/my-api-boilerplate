<?php

declare(strict_types=1);

namespace Lahatre\Sale\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Customer\Models\Customer;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Models\Sale;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * A draft without lines, in the counter mode, paid at once.
 *
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id'          => $organizationId,
            'number'                   => fake()->unique()->numerify('S-'.now()->year.'-9#####'),
            'status'                   => SaleStatus::Draft,
            'version'                  => 1,
            'customer_id'              => fn (array $attributes): string => Customer::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'customer_name'            => fn (array $attributes): string => (string) Customer::query()->whereKey($attributes['customer_id'])->value('name'),
            'currency_code'            => 'XOF',
            'mode'                     => SaleMode::Counter,
            'required_payment_percent' => 100,
            'lines_base_total'         => 0,
            'lines_tax_total'          => 0,
            'lines_total'              => 0,
            'total'                    => 0,
        ];
    }

    public function status(SaleStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
