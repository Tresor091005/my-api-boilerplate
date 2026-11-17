<?php

declare(strict_types=1);

namespace Lahatre\Sale\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Sale\Database\Factories\SaleLineFactory;
use Lahatre\Sale\Enums\FulfillmentStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * Amounts are minor units of the sale's currency.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $sale_id
 * @property int $position
 * @property string $catalog_item_id
 * @property int $quantity in the ratio-1 base unit; `display_unit_code` is the unit sold in
 * @property CatalogItemType $item_type
 * @property string $item_name
 * @property string $item_sku
 * @property string $display_unit_code
 * @property string $unit_name
 * @property string $price_level_id
 * @property string $price_level_name
 * @property int $unit_amount
 * @property string|null $tax_profile_id
 * @property int $amount
 * @property int $base_amount
 * @property int $included_tax_amount
 * @property int $added_tax_amount
 * @property int $total_amount
 * @property array<string, mixed> $tax_result
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|SaleLine newModelQuery()
 * @method static Builder<static>|SaleLine newQuery()
 * @method static Builder<static>|SaleLine query()
 * @method static SaleLineFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SaleLine extends Model
{
    use SharedTraits;

    protected $table = 'sale_lines';

    protected $fillable = [
        'organization_id',
        'sale_id',
        'position',
        'catalog_item_id',
        'quantity',
        'item_type',
        'item_name',
        'item_sku',
        'display_unit_code',
        'unit_name',
        'price_level_id',
        'price_level_name',
        'unit_amount',
        'tax_profile_id',
        'amount',
        'base_amount',
        'included_tax_amount',
        'added_tax_amount',
        'total_amount',
        'tax_result',
    ];

    protected $casts = [
        'id'                  => 'string',
        'organization_id'     => 'string',
        'sale_id'             => 'string',
        'position'            => 'integer',
        'catalog_item_id'     => 'string',
        'quantity'            => 'integer',
        'item_type'           => CatalogItemType::class,
        'item_name'           => 'string',
        'item_sku'            => 'string',
        'display_unit_code'   => 'string',
        'unit_name'           => 'string',
        'price_level_id'      => 'string',
        'price_level_name'    => 'string',
        'unit_amount'         => 'integer',
        'tax_profile_id'      => 'string',
        'amount'              => 'integer',
        'base_amount'         => 'integer',
        'included_tax_amount' => 'integer',
        'added_tax_amount'    => 'integer',
        'total_amount'        => 'integer',
        'tax_result'          => 'array',
        'created_at'          => 'immutable_datetime',
        'updated_at'          => 'immutable_datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class)
            ->where('sale_sales.organization_id', currentOrganizationId());
    }

    /** @return HasMany<FulfillmentLine, $this> the lines of completed fulfillments */
    public function deliveredLines(): HasMany
    {
        return $this->hasMany(FulfillmentLine::class)
            ->where('sale_fulfillment_lines.organization_id', currentOrganizationId())
            ->whereHas('fulfillment', fn (Builder $fulfillment): Builder => $fulfillment->where('status', FulfillmentStatus::Completed));
    }

    /** @return HasMany<SaleHold, $this> */
    public function holds(): HasMany
    {
        return $this->hasMany(SaleHold::class)
            ->where('sale_holds.organization_id', currentOrganizationId());
    }

    /** The price level the line was sold at, even deleted since. */
    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class)
            ->withTrashed()
            ->where('catalog_price_levels.organization_id', currentOrganizationId());
    }
}
