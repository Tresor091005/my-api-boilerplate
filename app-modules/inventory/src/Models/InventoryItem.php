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
use Lahatre\Inventory\Database\Factories\InventoryItemFactory;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $itemable_type
 * @property string $itemable_id
 * @property string|null $sku
 * @property string $base_unit_code
 * @property DeductionStrategy|null $deduction_strategy
 * @property bool $is_expirable
 * @property bool $stock_tracking_enabled
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Model|\Eloquent $itemable
 * @property-read Collection<int, InventoryStock> $stocks
 * @property-read int|null $stocks_count
 * @property-read Collection<int, InventoryStock> $stockSummaries
 * @property-read Collection<int, InventoryMovement> $movements
 * @property-read int|null $movements_count
 *
 * @method static Builder<static>|InventoryItem newModelQuery()
 * @method static Builder<static>|InventoryItem newQuery()
 * @method static Builder<static>|InventoryItem query()
 * @method static Builder<static>|InventoryItem whereId($value)
 * @method static Builder<static>|InventoryItem whereOrganizationId($value)
 * @method static Builder<static>|InventoryItem whereItemableType($value)
 * @method static Builder<static>|InventoryItem whereItemableId($value)
 * @method static Builder<static>|InventoryItem onlyTrashed()
 * @method static Builder<static>|InventoryItem whereCreatedAt($value)
 * @method static Builder<static>|InventoryItem whereDeletedAt($value)
 * @method static Builder<static>|InventoryItem whereUpdatedAt($value)
 * @method static Builder<static>|InventoryItem withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|InventoryItem withoutTrashed()
 * @method static Builder<static>|InventoryItem whereBaseUnitCode($value)
 * @method static Builder<static>|InventoryItem whereIsActive($value)
 * @method static Builder<static>|InventoryItem whereSku($value)
 * @method static InventoryItemFactory factory($count = null, $state = [])
 * @method static Builder<static>|InventoryItem whereDeductionStrategy($value)
 *
 * @property-read int|null $active_stock_location_summaries_count
 * @property-read int|null $active_stocks_count
 * @property-read int|null $stock_summaries_count
 *
 * @method static Builder<static>|InventoryItem whereIsExpirable($value)
 * @method static Builder<static>|InventoryItem whereStockTrackingEnabled($value)
 *
 * @mixin \Eloquent
 */
class InventoryItem extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'inventory_items';

    protected $fillable = [
        'organization_id',
        'itemable_type',
        'itemable_id',
        'sku',
        'base_unit_code',
        'deduction_strategy',
        'is_expirable',
        'stock_tracking_enabled',
    ];

    protected $casts = [
        'id'                     => 'string',
        'organization_id'        => 'string',
        'itemable_type'          => 'string',
        'itemable_id'            => 'string',
        'sku'                    => 'string',
        'base_unit_code'         => 'string',
        'deduction_strategy'     => DeductionStrategy::class,
        'is_expirable'           => 'boolean',
        'stock_tracking_enabled' => 'boolean',
        'created_at'             => 'immutable_datetime',
        'updated_at'             => 'immutable_datetime',
        'deleted_at'             => 'immutable_datetime',
    ];

    public function itemable(): MorphTo
    {
        return $this->morphTo('itemable', 'itemable_type', 'itemable_id')
            ->where('organization_id', currentOrganizationId());
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'item_id', 'id')
            ->where('inventory_stocks.organization_id', currentOrganizationId());
    }

    /**
     * Aggregated active stocks grouped by location for lightweight "summary" reads.
     *
     * Note: This returns InventoryStock models with only the aggregated attributes selected.
     */
    public function stockSummaries(): HasMany
    {
        return $this->stocks()->where('inventory_stocks.remaining', '>', 0)
            ->select([
                'inventory_stocks.item_id',
                'inventory_stocks.location_id',
                DB::raw('SUM(inventory_stocks.remaining) as total_remaining'),
                DB::raw('COUNT(*) as active_lots_count'),
            ])
            ->groupBy('inventory_stocks.item_id', 'inventory_stocks.location_id')
            ->orderBy('inventory_stocks.location_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id', 'id')
            ->where('inventory_movements.organization_id', currentOrganizationId());
    }
}
