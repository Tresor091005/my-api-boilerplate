<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\CatalogItemFactory;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Inventory\Contracts\HasInventoryItem;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Traits\InteractsWithInventoryItem;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property CatalogItemType $item_type
 * @property string $sku
 * @property string $unit_group_id
 * @property bool $is_stockable
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read UnitGroup $unitGroup
 * @property-read InventoryItem|null $inventoryItem
 *
 * @method static Builder<static>|CatalogItem newModelQuery()
 * @method static Builder<static>|CatalogItem newQuery()
 * @method static Builder<static>|CatalogItem query()
 * @method static CatalogItemFactory factory($count = null, $state = [])
 * @method static Builder<static>|CatalogItem withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|CatalogItem onlyTrashed()
 * @method static Builder<static>|CatalogItem withoutTrashed()
 * @method static Builder<static>|CatalogItem whereCreatedAt($value)
 * @method static Builder<static>|CatalogItem whereDeletedAt($value)
 * @method static Builder<static>|CatalogItem whereId($value)
 * @method static Builder<static>|CatalogItem whereIsActive($value)
 * @method static Builder<static>|CatalogItem whereItemType($value)
 * @method static Builder<static>|CatalogItem whereOrganizationId($value)
 * @method static Builder<static>|CatalogItem whereSku($value)
 * @method static Builder<static>|CatalogItem whereUnitGroupId($value)
 * @method static Builder<static>|CatalogItem whereUpdatedAt($value)
 * @method static Builder<static>|CatalogItem whereIsStockable($value)
 *
 * @mixin \Eloquent
 */
class CatalogItem extends Model implements HasInventoryItem
{
    use InteractsWithInventoryItem;
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_items';

    protected $fillable = [
        'organization_id',
        'item_type',
        'sku',
        'unit_group_id',
        'is_stockable',
        'is_active',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'item_type'       => CatalogItemType::class,
        'sku'             => 'string',
        'unit_group_id'   => 'string',
        'is_stockable'    => 'boolean',
        'is_active'       => 'boolean',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function getUnitGroupId(): string
    {
        return $this->unit_group_id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function unitGroup(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class, 'unit_group_id', 'id');
    }
}
