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
        if ($stock->organization_id !== $organizationId) {
            throw OrganizationScopeException::mismatch($organizationId, $stock->organization_id);
        }

        return DB::transaction(function () use ($stock, $metadata): InventoryStock {
            $stock->metadata = $metadata;
            $stock->save();

            return $stock->refresh()->load(responseRelationsToLoad());
        });
    }
}
