<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Catalog\Enums\StockTransferStatus;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Models\StockTransfer;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'                       => (string) Str::uuid7(),
            'organization_id'          => currentOrganizationId(),
            'source_location_id'       => StockLocation::factory(['organization_id' => currentOrganizationId()]),
            'destination_location_id'  => StockLocation::factory(['organization_id' => currentOrganizationId()]),
            'status'                   => StockTransferStatus::Draft->value,
            'inventory_transaction_id' => null,
            'reversal_transaction_id'  => null,
            'completed_at'             => null,
            'cancelled_at'             => null,
        ];
    }
}
