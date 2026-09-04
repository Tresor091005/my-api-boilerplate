<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\StockTransfer;
use Lahatre\Catalog\Models\StockTransferLine;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

/**
 * @extends Factory<StockTransferLine>
 */
class StockTransferLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organizationId = currentOrganizationId();
        $unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
        $unit = Unit::factory()->create([
            'organization_id' => null,
            'group_id'        => $unitGroup->id,
            'ratio'           => 1,
        ]);
        $catalogItem = CatalogItem::factory()->create([
            'organization_id' => $organizationId,
            'unit_group_id'   => $unitGroup->id,
        ]);
        $productVariant = ProductVariant::factory()
            ->forCatalogItem($catalogItem)
            ->create();

        return [
            'id'                => (string) Str::uuid7(),
            'organization_id'   => $organizationId,
            'stock_transfer_id' => StockTransfer::factory(['organization_id' => $organizationId]),
            'catalog_item_type' => CatalogItemType::ProductVariant,
            'catalog_item_id'   => $productVariant->id,
            'position'          => 0,
            'quantity'          => 1,
            'display_unit_code' => $unit->code,
            'strategy'          => null,
            'stock_ids'         => null,
        ];
    }
}
