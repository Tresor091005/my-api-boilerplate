<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Inventory\Contracts\InventoryInterface;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Master\Contracts\MasterInterface;

final class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = currentOrganizationId();
        $location = StockLocation::query()
            ->where('organization_id', $organizationId)
            ->where('name', 'Main Warehouse')
            ->with('inventoryLocation')
            ->first();

        if ($location?->inventoryLocation === null || !$location->inventoryLocation->is_active) {
            return;
        }

        $referenceId = $organizationId;
        if (InventoryTransaction::query()
            ->where('organization_id', $organizationId)
            ->where('reference_type', 'development_seed')
            ->where('reference_id', $referenceId)
            ->exists()) {
            return;
        }

        $unit = app(MasterInterface::class)->unit('g');
        $items = CatalogItem::query()
            ->where('organization_id', $organizationId)
            ->whereIn('sku', [
                'IP15P-BLA-128',
                'USB-C-HUB-SIL',
                'MBP16-SG-16-512',
            ])
            ->with('inventoryItem')
            ->get()
            ->filter(fn (CatalogItem $item): bool => $item->inventoryItem !== null);

        if ($items->isEmpty()) {
            return;
        }

        app(InventoryInterface::class)->recordTransaction([
            'reference_type'   => 'development_seed',
            'reference_id'     => $referenceId,
            'idempotency_key'  => $referenceId,
            'transaction_type' => TransactionType::In->value,
            'movements'        => $items->map(fn (CatalogItem $item): array => [
                'item_id'       => $item->inventoryItem->id,
                'location_id'   => $location->inventoryLocation->id,
                'type'          => MovementType::In->value,
                'quantity'      => $item->sku === 'MBP16-SG-16-512' ? 5 : 25,
                'unit_code'     => $unit->code,
                'total_cost'    => $item->sku === 'MBP16-SG-16-512' ? 1_500_000 : 25_000,
                'currency_code' => 'XOF',
            ])->values()->all(),
        ]);
    }
}
