<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    use ResolvesOrganizationId;

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        $idempotencyKey = (string) Str::uuid7();

        return [
            'organization_id'  => $organizationId,
            'idempotency_key'  => $idempotencyKey,
            'payload_hash'     => hash('sha256', $idempotencyKey),
            'reference_type'   => 'test_reference',
            'reference_id'     => (string) Str::uuid7(),
            'transaction_type' => fake()->randomElement(TransactionType::cases()),
            'metadata'         => null,
        ];
    }
}
