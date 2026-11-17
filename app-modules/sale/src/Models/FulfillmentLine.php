<?php

declare(strict_types=1);

namespace Lahatre\Sale\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $fulfillment_id
 * @property string $sale_line_id
 * @property string|null $stock_location_id the warehouse, empty for an item whose stock is not tracked
 * @property int $quantity in the ratio-1 base unit, like the sale line
 * @property list<string>|null $stock_ids the lots to take, in order; empty lets inventory choose
 * @property int|null $total_cost what the goods cost when they left, in minor units
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Fulfillment $fulfillment
 * @property-read SaleLine $saleLine
 * @property-read StockLocation|null $stockLocation
 *
 * @mixin \Eloquent
 */
class FulfillmentLine extends Model
{
    use SharedTraits;

    protected $table = 'sale_fulfillment_lines';

    protected $fillable = [
        'organization_id',
        'fulfillment_id',
        'sale_line_id',
        'stock_location_id',
        'quantity',
        'stock_ids',
        'total_cost',
    ];

    protected $casts = [
        'id'                => 'string',
        'organization_id'   => 'string',
        'fulfillment_id'    => 'string',
        'sale_line_id'      => 'string',
        'stock_location_id' => 'string',
        'quantity'          => 'integer',
        'stock_ids'         => 'array',
        'total_cost'        => 'integer',
        'created_at'        => 'immutable_datetime',
        'updated_at'        => 'immutable_datetime',
    ];

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class)
            ->where('sale_fulfillments.organization_id', currentOrganizationId());
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class)
            ->where('sale_lines.organization_id', currentOrganizationId());
    }

    /** The warehouse, even deleted since. */
    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class)
            ->withTrashed()
            ->where('catalog_stock_locations.organization_id', currentOrganizationId());
    }
}
