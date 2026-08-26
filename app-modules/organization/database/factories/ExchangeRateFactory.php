<?php

declare(strict_types=1);

namespace Lahatre\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Organization\Models\ExchangeRate;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id'      => Organization::factory(),
            'source_currency_code' => 'USD',
            'target_currency_code' => 'XOF',
            'context'              => 'default',
            'rate'                 => '610.500000000000',
            'effective_at'         => now()->addDay(),
        ];
    }
}
