<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Lahatre\Inventory\Database\Factories\InventoryLocationFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $external_type
 * @property string $external_id
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, InventoryStock> $stocks
 * @property-read int|null $stocks_count
 * @property-read Collection<int, InventoryMovement> $movements
 * @property-read int|null $movements_count
 *
 * @method static Builder<static>|InventoryLocation newModelQuery()
 * @method static Builder<static>|InventoryLocation newQuery()
 * @method static Builder<static>|InventoryLocation query()
 * @method static Builder<static>|InventoryLocation whereId($value)
 * @method static Builder<static>|InventoryLocation whereExternalType($value)
 * @method static Builder<static>|InventoryLocation whereExternalId($value)
 * @method static Builder<static>|InventoryLocation onlyTrashed()
 * @method static Builder<static>|InventoryLocation whereCreatedAt($value)
 * @method static Builder<static>|InventoryLocation whereDeletedAt($value)
 * @method static Builder<static>|InventoryLocation whereUpdatedAt($value)
 * @method static Builder<static>|InventoryLocation withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|InventoryLocation withoutTrashed()
 * @method static Builder<static>|InventoryLocation whereIsActive($value)
 *
 * @property-read Model|\Eloquent $external
 *
 * @method static InventoryLocationFactory factory($count = null, $state = [])
 *
 * @property-read Collection<int, InventoryStock> $activeStocks
 * @property-read int|null $active_stocks_count
 * @property-read Collection<int, InventoryStock> $stockSummaries
 *
 * @mixin \Eloquent
 */
class InventoryLocation extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'inventory_locations';

    protected $fillable = [
        'external_type',
        'external_id',
        'is_active',
    ];

    protected $casts = [
        'id'            => 'string',
        'external_type' => 'string',
        'external_id'   => 'string',
        'is_active'     => 'boolean',
        'created_at'    => 'immutable_datetime',
        'updated_at'    => 'immutable_datetime',
        'deleted_at'    => 'immutable_datetime',
    ];

    public function external(): MorphTo
    {
        return $this->morphTo('external', 'external_type', 'external_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'location_id', 'id');
    }

    public function activeStocks(): HasMany
    {
        return $this->stocks()->where('remaining', '>', 0);
    }

    /**
     * Aggregated active stocks grouped by item for lightweight summary reads.
     */
    public function stockSummaries(): HasMany
    {
        return $this->activeStocks()
            ->select([
                'location_id',
                'item_id',
                DB::raw('SUM(remaining) as total_remaining'),
                DB::raw('COUNT(*) as active_lots_count'),
            ])
            ->groupBy('location_id', 'item_id')
            ->orderBy('item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'location_id', 'id');
    }
}
