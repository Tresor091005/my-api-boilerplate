<?php

declare(strict_types=1);

namespace Lahatre\Master\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Master\Models\Currency;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    public function definition(): array
    {
        do {
            $code = Str::toUpper($this->faker->lexify('???'));
        } while (Currency::query()->where('code', $code)->exists());

        return [
            'code'      => $code,
            'name'      => $this->faker->word(),
            'symbol'    => $this->faker->lexify('??'),
            'precision' => 2,
        ];
    }
}
