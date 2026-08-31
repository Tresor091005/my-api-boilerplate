<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Inventory\Database\Factories\InventoryStockFactory;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $item_id
 * @property string $location_id
 * @property int $unit_cost
 * @property int $cost_remainder
 * @property string $currency_code
 * @property int $quantity
 * @property int $remaining
 * @property string $base_unit_code
 * @property CarbonImmutable|null $expiration_date
 * @property array|null $metadata
 * @property array|null $exchange_metadata
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read InventoryItem $item
 * @property-read InventoryLocation $location
 * @property-read Unit $unit
 * @property-read Currency $currency
 * @property-read Collection<int, InventoryMovement> $movements
 * @property-read int|null $movements_count
 *
 * @method static Builder<static>|InventoryStock newModelQuery()
 * @method static Builder<static>|InventoryStock newQuery()
 * @method static Builder<static>|InventoryStock query()
 * @method static Builder<static>|InventoryStock whereId($value)
 * @method static Builder<static>|InventoryStock whereOrganizationId($value)
 * @method static Builder<static>|InventoryStock whereItemId($value)
 * @method static Builder<static>|InventoryStock whereLocationId($value)
 * @method static Builder<static>|InventoryStock whereRemaining($value)
 * @method static Builder<static>|InventoryStock onlyTrashed()
 * @method static Builder<static>|InventoryStock whereCreatedAt($value)
 * @method static Builder<static>|InventoryStock whereCurrencyCode($value)
 * @method static Builder<static>|InventoryStock whereDeletedAt($value)
 * @method static Builder<static>|InventoryStock whereMetadata($value)
 * @method static Builder<static>|InventoryStock whereExpirationDate($value)
 * @method static Builder<static>|InventoryStock whereQuantity($value)
 * @method static Builder<static>|InventoryStock whereUnitCost($value)
 * @method static Builder<static>|InventoryStock whereBaseUnitCode($value)
 * @method static Builder<static>|InventoryStock whereUpdatedAt($value)
 * @method static Builder<static>|InventoryStock withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|InventoryStock withoutTrashed()
 * @method static InventoryStockFactory factory($count = null, $state = [])
 * @method static Builder<static>|InventoryStock whereCostRemainder($value)
 * @method static Builder<static>|InventoryStock whereExchangeMetadata($value)
 *
 * @mixin \Eloquent
 */
class InventoryStock extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'inventory_stocks';

    protected $fillable = [
        'organization_id',
        'item_id',
        'location_id',
        'unit_cost',
        'cost_remainder',
        'currency_code',
        'quantity',
        'remaining',
        'base_unit_code',
        'expiration_date',
        'metadata',
        'exchange_metadata',
    ];

    protected $casts = [
        'id'                => 'string',
        'organization_id'   => 'string',
        'item_id'           => 'string',
        'location_id'       => 'string',
        'unit_cost'         => 'integer',
        'cost_remainder'    => 'integer',
        'currency_code'     => 'string',
        'quantity'          => 'integer',
        'remaining'         => 'integer',
        'base_unit_code'    => 'string',
        'expiration_date'   => 'immutable_date',
        'metadata'          => 'array',
        'exchange_metadata' => 'array',
        'created_at'        => 'immutable_datetime',
        'updated_at'        => 'immutable_datetime',
        'deleted_at'        => 'immutable_datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id', 'id')
            ->where('organization_id', currentOrganizationId());
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id', 'id')
            ->where('organization_id', currentOrganizationId());
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_code', 'code')->withTrashed();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code')->withTrashed();
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'stock_id', 'id')
            ->where('organization_id', currentOrganizationId());
    }
}
