<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Lahatre\Billing\Contracts\ProvidesFeatures;
use Lahatre\Billing\Registries\FeatureDefinition;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Models\StockTransfer;

final class CatalogFeatures implements ProvidesFeatures
{
    public function definitions(): array
    {
        return [
            FeatureDefinition::boolean('catalog.bundle_assembly'),
            FeatureDefinition::capacity('catalog.stock_locations', [self::class, 'stockLocations']),
            FeatureDefinition::capacity('catalog.stock_transfers', [self::class, 'stockTransfers'], CarbonInterval::days(30)),
        ];
    }

    public function stockLocations(string $organizationId): int
    {
        return StockLocation::query()
            ->where('organization_id', $organizationId)
            ->whereHas('inventoryLocation', fn (Builder $location): Builder => $location->where('is_active', true))
            ->count();
    }

    public function stockTransfers(string $organizationId, CarbonImmutable $since): int
    {
        // a deleted draft was still used: deleting it gives nothing back
        return StockTransfer::withTrashed()
            ->where('organization_id', $organizationId)
            ->where('created_at', '>', $since)
            ->count();
    }
}
