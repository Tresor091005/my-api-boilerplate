<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lahatre\Inventory\Database\Factories\InventoryMovementFactory;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property MovementType $movement_type
 * @property string $transaction_id
 * @property string|null $link_id
 * @property string $item_id
 * @property string $stock_id
 * @property string $location_id
 * @property int $quantity
 * @property string $unit_code
 * @property int $total_cost
 * @property string $currency_code
 * @property CarbonImmutable|null $expiration_date
 * @property array|null $metadata
 * @property array|null $exchange_metadata
 * @property array|null $stock_metadata_snapshot
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
 * @method static Builder<static>|InventoryMovement whereOrganizationId($value)
 * @method static Builder<static>|InventoryMovement whereMovementType($value)
 * @method static Builder<static>|InventoryMovement whereTransactionId($value)
 * @method static Builder<static>|InventoryMovement whereStockId($value)
 * @method static Builder<static>|InventoryMovement whereCreatedAt($value)
 * @method static Builder<static>|InventoryMovement whereCurrencyCode($value)
 * @method static Builder<static>|InventoryMovement whereItemId($value)
 * @method static Builder<static>|InventoryMovement whereLocationId($value)
 * @method static Builder<static>|InventoryMovement whereExpirationDate($value)
 * @method static Builder<static>|InventoryMovement whereQuantity($value)
 * @method static Builder<static>|InventoryMovement whereUnitCode($value)
 * @method static Builder<static>|InventoryMovement whereUpdatedAt($value)
 * @method static InventoryMovementFactory factory($count = null, $state = [])
 * @method static Builder<static>|InventoryMovement whereMetadata($value)
 * @method static Builder<static>|InventoryMovement whereExpirationDate($value)
 * @method static Builder<static>|InventoryMovement whereLinkId($value)
 * @method static Builder<static>|InventoryMovement whereStockMetadataSnapshot($value)
 * @method static Builder<static>|InventoryMovement whereTotalCost($value)
 *
 * @mixin \Eloquent
 */
class InventoryMovement extends Model
{
    use SharedTraits;

    protected $table = 'inventory_movements';

    protected $fillable = [
        'organization_id',
        'movement_type',
        'transaction_id',
        'link_id',
        'item_id',
        'stock_id',
        'location_id',
        'quantity',
        'unit_code',
        'total_cost',
        'currency_code',
        'expiration_date',
        'metadata',
        'exchange_metadata',
        'stock_metadata_snapshot',
    ];

    protected $casts = [
        'id'                      => 'string',
        'organization_id'         => 'string',
        'movement_type'           => MovementType::class,
        'transaction_id'          => 'string',
        'link_id'                 => 'string',
        'item_id'                 => 'string',
        'stock_id'                => 'string',
        'location_id'             => 'string',
        'quantity'                => 'integer',
        'unit_code'               => 'string',
        'total_cost'              => 'integer',
        'currency_code'           => 'string',
        'expiration_date'         => 'immutable_date',
        'metadata'                => 'array',
        'exchange_metadata'       => 'array',
        'stock_metadata_snapshot' => 'array',
        'created_at'              => 'immutable_datetime',
        'updated_at'              => 'immutable_datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'transaction_id', 'id')
            ->where('organization_id', currentOrganizationId());
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id', 'id')
            ->where('organization_id', currentOrganizationId());
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'stock_id', 'id')
            ->where('organization_id', currentOrganizationId());
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id', 'id')
            ->where('organization_id', currentOrganizationId());
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_code', 'code')->withTrashed();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code')->withTrashed();
    }
}
