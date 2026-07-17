<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
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
        $organizationId = getPermissionsTeamId() ?: (string) Str::uuid7();

        if (!getPermissionsTeamId()) {
            DB::table('organization_organizations')->insert([
                'id'         => $organizationId,
                'name'       => 'Factory Organization '.$organizationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

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
