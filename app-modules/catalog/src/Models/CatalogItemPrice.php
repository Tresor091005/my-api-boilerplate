<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\CatalogItemPriceFactory;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Traits\SharedTraits;
use Lahatre\Tax\Models\Tax;

/**
 * The price of one sale unit of an item at one price level, in minor units of
 * the level's currency. The amount already contains its included taxes; the
 * item's other taxes are added on top when it is sold.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $catalog_item_id
 * @property string $price_level_id
 * @property string $unit_id
 * @property int $amount
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read CatalogItem $catalogItem
 * @property-read PriceLevel $priceLevel
 * @property-read Unit $unit
 * @property-read Collection<int, Tax> $includedTaxes
 *
 * @method static Builder<static>|CatalogItemPrice newModelQuery()
 * @method static Builder<static>|CatalogItemPrice newQuery()
 * @method static Builder<static>|CatalogItemPrice query()
 * @method static Builder<static>|CatalogItemPrice withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|CatalogItemPrice onlyTrashed()
 * @method static Builder<static>|CatalogItemPrice withoutTrashed()
 * @method static CatalogItemPriceFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class CatalogItemPrice extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_item_prices';

    protected $fillable = [
        'organization_id',
        'catalog_item_id',
        'price_level_id',
        'unit_id',
        'amount',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'catalog_item_id' => 'string',
        'price_level_id'  => 'string',
        'unit_id'         => 'string',
        'amount'          => 'integer',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id', 'id')
            ->where('catalog_items.organization_id', currentOrganizationId());
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class, 'price_level_id', 'id')
            ->where('catalog_price_levels.organization_id', currentOrganizationId());
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id')
            ->withTrashed()
            ->where(function (Builder $query): void {
                $query->whereNull('master_units.organization_id')
                    ->orWhere('master_units.organization_id', currentOrganizationId());
            });
    }

    /**
     * The taxes the amount already contains. A tax deleted since is left out.
     */
    public function includedTaxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'catalog_item_price_taxes', 'catalog_item_price_id', 'tax_id')
            ->using(CatalogItemPriceTax::class)
            ->withPivot('organization_id')
            ->wherePivot('organization_id', currentOrganizationId())
            ->where('tax_taxes.organization_id', currentOrganizationId());
    }
}
