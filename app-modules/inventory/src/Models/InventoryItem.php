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
use Lahatre\Inventory\Database\Factories\InventoryItemFactory;
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $itemable_type
 * @property string $itemable_id
 * @property string|null $sku
 * @property string|null $base_unit_code
 * @property DeductionStrategy $deduction_strategy
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Model|\Eloquent $itemable
 * @property-read Collection<int, InventoryStock> $stocks
 * @property-read int|null $stocks_count
 * @property-read Collection<int, InventoryMovement> $movements
 * @property-read int|null $movements_count
 *
 * @method static Builder<static>|InventoryItem newModelQuery()
 * @method static Builder<static>|InventoryItem newQuery()
 * @method static Builder<static>|InventoryItem query()
 * @method static Builder<static>|InventoryItem whereId($value)
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
 * @mixin \Eloquent
 */
class InventoryItem extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'inventory_items';

    protected $fillable = [
        'itemable_type',
        'itemable_id',
        'sku',
        'base_unit_code',
        'deduction_strategy',
        'is_active',
    ];

    protected $casts = [
        'id'                 => 'string',
        'itemable_type'      => 'string',
        'itemable_id'        => 'string',
        'sku'                => 'string',
        'base_unit_code'     => 'string',
        'deduction_strategy' => DeductionStrategy::class,
        'is_active'          => 'boolean',
        'created_at'         => 'immutable_datetime',
        'updated_at'         => 'immutable_datetime',
        'deleted_at'         => 'immutable_datetime',
    ];

    public function itemable(): MorphTo
    {
        return $this->morphTo('itemable', 'itemable_type', 'itemable_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'item_id', 'id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id', 'id');
    }
}
