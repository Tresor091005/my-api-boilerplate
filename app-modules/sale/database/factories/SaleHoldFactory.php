<?php

declare(strict_types=1);

namespace Lahatre\Sale\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Sale\Models\SaleHold;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * A row only: no stock is moved. Tests that need the stock go through confirmation.
 *
 * @extends Factory<SaleHold>
 */
class SaleHoldFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id'          => $this->resolveOrganizationId(),
            'sale_line_id'             => fn (array $attributes): string => SaleLine::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'sale_id'                  => fn (array $attributes): string => (string) SaleLine::query()->whereKey($attributes['sale_line_id'])->value('sale_id'),
            'stock_location_id'        => fn (array $attributes): string => StockLocation::factory()->create(['organization_id' => $attributes['organization_id']])->id,
            'quantity'                 => fn (array $attributes): int => (int) SaleLine::query()->whereKey($attributes['sale_line_id'])->value('quantity'),
            'released_quantity'        => 0,
            'inventory_transaction_id' => fn (array $attributes): string => InventoryTransaction::factory()->create([
                'organization_id'  => $attributes['organization_id'],
                'transaction_type' => TransactionType::Transfer,
            ])->id,
            'release_transaction_id' => null,
        ];
    }
}
