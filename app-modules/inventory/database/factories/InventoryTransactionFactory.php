<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Organization\Models\Organization;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference_type'   => Organization::class,
            'reference_id'     => Organization::factory(),
            'transaction_type' => fake()->randomElement(TransactionType::cases()),
            'metadata'         => null,
        ];
    }
}
