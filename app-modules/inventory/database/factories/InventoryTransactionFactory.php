<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryTransaction;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference_type'   => 'test_reference',
            'reference_id'     => (string) Str::uuid7(),
            'transaction_type' => fake()->randomElement(TransactionType::cases()),
            'metadata'         => null,
        ];
    }
}
