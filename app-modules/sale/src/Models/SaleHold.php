<?php

declare(strict_types=1);

namespace Lahatre\Sale\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * What a `bill_and_hold` sale set aside at confirmation, per line and warehouse, in the warehouse's
 * customer hold location. What remains held is `quantity` less what was delivered from it and `released_quantity`.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $sale_id
 * @property string $sale_line_id
 * @property string $stock_location_id the warehouse
 * @property int $quantity in the ratio-1 base unit
 * @property int $released_quantity given back to the warehouse when the sale was cancelled
 * @property string $inventory_transaction_id
 * @property string|null $release_transaction_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Sale $sale
 * @property-read SaleLine $saleLine
 * @property-read StockLocation $stockLocation
 *
 * @mixin \Eloquent
 */
class SaleHold extends Model
{
    use SharedTraits;

    protected $table = 'sale_holds';

    protected $fillable = [
        'organization_id',
        'sale_id',
        'sale_line_id',
        'stock_location_id',
        'quantity',
        'released_quantity',
        'inventory_transaction_id',
        'release_transaction_id',
    ];

    protected $casts = [
        'id'                       => 'string',
        'organization_id'          => 'string',
        'sale_id'                  => 'string',
        'sale_line_id'             => 'string',
        'stock_location_id'        => 'string',
        'quantity'                 => 'integer',
        'released_quantity'        => 'integer',
        'inventory_transaction_id' => 'string',
        'release_transaction_id'   => 'string',
        'created_at'               => 'immutable_datetime',
        'updated_at'               => 'immutable_datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)
            ->where('sale_sales.organization_id', currentOrganizationId());
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
