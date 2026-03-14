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
use Lahatre\Catalog\Models\Currency;
use Lahatre\Catalog\Models\Unit;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $item_id
 * @property string $location_id
 * @property int $unit_cost
 * @property string $currency_code
 * @property int $quantity
 * @property int $remaining
 * @property string $unit_id
 * @property CarbonImmutable|null $peremption_date
 * @property array|null $metadata
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
 * @method static Builder<static>|InventoryStock whereItemId($value)
 * @method static Builder<static>|InventoryStock whereLocationId($value)
 * @method static Builder<static>|InventoryStock whereRemaining($value)
 *
 * @mixin \Eloquent
 */
class InventoryStock extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'inventory_stocks';

    protected $fillable = [
        'item_id',
        'location_id',
        'unit_cost',
        'currency_code',
        'quantity',
        'remaining',
        'unit_id',
        'peremption_date',
        'metadata',
    ];

    protected $casts = [
        'id'              => 'string',
        'item_id'         => 'string',
        'location_id'     => 'string',
        'unit_cost'       => 'integer',
        'currency_code'   => 'string',
        'quantity'        => 'integer',
        'remaining'       => 'integer',
        'unit_id'         => 'string',
        'peremption_date' => 'immutable_datetime',
        'metadata'        => 'array',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id', 'id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id'); // TODO : can't depend on catalog
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'stock_id', 'id');
    }
}
