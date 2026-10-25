<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * Inventory item rendered through response selection. InventoryItemResource stays for modules not
 * migrated yet.
 */
final class InventoryItemApiResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('sku')
            ->field('base_unit_code')
            ->field('deduction_strategy')
            ->field('is_expirable')
            ->field('stock_tracking_enabled')
            ->field('total_remaining', relations: ['stockSummaries'], computedBy: fn (Model $item): int => (int) $item->getRelation('stockSummaries')->sum('total_remaining'))
            ->field('active_lots_count', relations: ['stockSummaries'], computedBy: fn (Model $item): int => (int) $item->getRelation('stockSummaries')->sum('active_lots_count'))
            ->field('locations', relations: ['stockSummaries'], computedBy: fn (Model $item): array => $item->getRelation('stockSummaries')
                ->map(fn (Model $summary): array => [
                    'location_id'       => $summary->getAttribute('location_id'),
                    'total_remaining'   => (int) $summary->getAttribute('total_remaining'),
                    'active_lots_count' => (int) $summary->getAttribute('active_lots_count'),
                ])
                ->values()
                ->all());
    }
}
