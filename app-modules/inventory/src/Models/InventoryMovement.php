<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Catalog\Models\Currency;
use Lahatre\Catalog\Models\Unit;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $movement_type
 * @property string $transaction_id
 * @property string $item_id
 * @property string $stock_id
 * @property string $location_id
 * @property int $quantity
 * @property string $unit_id
 * @property int $unit_cost
 * @property string $currency_code
 * @property CarbonImmutable|null $peremption_date
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read InventoryTransaction $transaction
 * @property-read InventoryItem $item
 * @property-read InventoryStock $stock
 * @property-read InventoryLocation $location
 * @property-read Unit $unit
 * @property-read Currency $currency
 *
 * @method static Builder<static>|InventoryMovement newModelQuery()
 * @method static Builder<static>|InventoryMovement newQuery()
 * @method static Builder<static>|InventoryMovement query()
 * @method static Builder<static>|InventoryMovement whereId($value)
 * @method static Builder<static>|InventoryMovement whereMovementType($value)
 * @method static Builder<static>|InventoryMovement whereTransactionId($value)
 * @method static Builder<static>|InventoryMovement whereStockId($value)
 * @method static Builder<static>|InventoryMovement whereCreatedAt($value)
 * @method static Builder<static>|InventoryMovement whereCurrencyCode($value)
 * @method static Builder<static>|InventoryMovement whereItemId($value)
 * @method static Builder<static>|InventoryMovement whereLocationId($value)
 * @method static Builder<static>|InventoryMovement wherePeremptionDate($value)
 * @method static Builder<static>|InventoryMovement whereQuantity($value)
 * @method static Builder<static>|InventoryMovement whereUnitCost($value)
 * @method static Builder<static>|InventoryMovement whereUnitId($value)
 * @method static Builder<static>|InventoryMovement whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InventoryMovement extends Model
{
    use SharedTraits;

    protected $table = 'inventory_movements';

    protected $fillable = [
        'movement_type',
        'transaction_id',
        'item_id',
        'stock_id',
        'location_id',
        'quantity',
        'unit_id',
        'unit_cost',
        'currency_code',
        'peremption_date',
    ];

    protected $casts = [
        'id'              => 'string',
        'movement_type'   => MovementType::class,
        'transaction_id'  => 'string',
        'item_id'         => 'string',
        'stock_id'        => 'string',
        'location_id'     => 'string',
        'quantity'        => 'integer',
        'unit_id'         => 'string',
        'unit_cost'       => 'integer',
        'currency_code'   => 'string',
        'peremption_date' => 'immutable_datetime',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'transaction_id', 'id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id', 'id');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id', 'id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id'); // TODO FIX catalog deps
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
