<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services\Stock;

use Illuminate\Support\Facades\DB;
use Lahatre\Inventory\Exceptions\OrganizationScopeException;
use Lahatre\Inventory\Models\InventoryStock;

class ManageInventoryStockService
{
    public function updateMetadata(InventoryStock $stock, ?array $metadata): InventoryStock
    {
        $organizationId = currentOrganizationId();

        return DB::transaction(function () use ($stock, $metadata, $organizationId): InventoryStock {
            $ownedStock = InventoryStock::query()
                ->where('organization_id', $organizationId)
                ->whereKey($stock->getKey())
                ->lockForUpdate()
                ->first();

            if ($ownedStock === null) {
                throw OrganizationScopeException::mismatch();
            }

            $ownedStock->metadata = $metadata;
            $ownedStock->save();

            return $ownedStock->refresh()->load(responseRelationsToLoad());
        });
    }
}
