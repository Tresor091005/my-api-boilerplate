<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
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
            'reference_type'   => Company::class,
            'reference_id'     => Company::factory(),
            'transaction_type' => fake()->randomElement(TransactionType::cases()),
            'metadata'         => null,
        ];
    }
}
