<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\StockTransferFactory;
use Lahatre\Catalog\Enums\StockTransferStatus;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $source_location_id
 * @property string $destination_location_id
 * @property StockTransferStatus $status
 * @property string|null $inventory_transaction_id
 * @property string|null $reversal_transaction_id
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, StockTransferLine> $lines
 * @property-read StockLocation|null $destinationLocation
 * @property-read InventoryTransaction|null $inventoryTransaction
 * @property-read int|null $lines_count
 * @property-read InventoryTransaction|null $reversalTransaction
 * @property-read StockLocation|null $sourceLocation
 *
 * @method static StockTransferFactory factory($count = null, $state = [])
 * @method static Builder<static>|StockTransfer newModelQuery()
 * @method static Builder<static>|StockTransfer newQuery()
 * @method static Builder<static>|StockTransfer onlyTrashed()
 * @method static Builder<static>|StockTransfer query()
 * @method static Builder<static>|StockTransfer whereCancelledAt($value)
 * @method static Builder<static>|StockTransfer whereCompletedAt($value)
 * @method static Builder<static>|StockTransfer whereCreatedAt($value)
 * @method static Builder<static>|StockTransfer whereDeletedAt($value)
 * @method static Builder<static>|StockTransfer whereDestinationLocationId($value)
 * @method static Builder<static>|StockTransfer whereId($value)
 * @method static Builder<static>|StockTransfer whereInventoryTransactionId($value)
 * @method static Builder<static>|StockTransfer whereOrganizationId($value)
 * @method static Builder<static>|StockTransfer whereReversalTransactionId($value)
 * @method static Builder<static>|StockTransfer whereSourceLocationId($value)
 * @method static Builder<static>|StockTransfer whereStatus($value)
 * @method static Builder<static>|StockTransfer whereUpdatedAt($value)
 * @method static Builder<static>|StockTransfer withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StockTransfer withoutTrashed()
 *
 * @mixin \Eloquent
 */
class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use HasFactory;

    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_stock_transfers';

    protected $fillable = [
        'organization_id',
        'source_location_id',
        'destination_location_id',
        'status',
        'inventory_transaction_id',
        'reversal_transaction_id',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'id'                       => 'string',
        'organization_id'          => 'string',
        'source_location_id'       => 'string',
        'destination_location_id'  => 'string',
        'status'                   => StockTransferStatus::class,
        'inventory_transaction_id' => 'string',
        'reversal_transaction_id'  => 'string',
        'completed_at'             => 'immutable_datetime',
        'cancelled_at'             => 'immutable_datetime',
        'created_at'               => 'immutable_datetime',
        'updated_at'               => 'immutable_datetime',
        'deleted_at'               => 'immutable_datetime',
    ];

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id')
            ->where('catalog_stock_locations.organization_id', currentOrganizationId());
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id')
            ->where('catalog_stock_locations.organization_id', currentOrganizationId());
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class)->orderBy('position');
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id')
            ->where('inventory_transactions.organization_id', currentOrganizationId());
    }

    public function reversalTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'reversal_transaction_id')
            ->where('inventory_transactions.organization_id', currentOrganizationId());
    }
}
