<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\BundleFactory;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $handle
 * @property string $name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, BundleItem> $items
 * @property-read int|null $items_count
 * @property-read CatalogItem $catalogItem
 * @property-read Collection<int, BundleStockOperation> $stockOperations
 *
 * @method static Builder<static>|Bundle newModelQuery()
 * @method static Builder<static>|Bundle newQuery()
 * @method static Builder<static>|Bundle query()
 * @method static Builder<static>|Bundle whereCreatedAt($value)
 * @method static Builder<static>|Bundle whereHandle($value)
 * @method static Builder<static>|Bundle whereId($value)
 * @method static Builder<static>|Bundle whereName($value)
 * @method static Builder<static>|Bundle whereUpdatedAt($value)
 * @method static BundleFactory factory($count = null, $state = [])
 * @method static Builder<static>|Bundle onlyTrashed()
 * @method static Builder<static>|Bundle whereOrganizationId($value)
 * @method static Builder<static>|Bundle withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Bundle withoutTrashed()
 * @method static Builder<static>|Bundle whereDeletedAt($value)
 *
 * @mixin \Eloquent
 */
class Bundle extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_bundles';

    protected $fillable = [
        'organization_id',
        'handle',
        'name',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'handle'          => 'string',
        'name'            => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'id', 'id')
            ->where('catalog_items.item_type', CatalogItemType::Bundle->value)
            ->where('catalog_items.organization_id', currentOrganizationId());
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundle_id', 'id')
            ->where('catalog_bundle_items.organization_id', currentOrganizationId());
    }

    public function stockOperations(): HasMany
    {
        return $this->hasMany(BundleStockOperation::class, 'bundle_id', 'id')
            ->where('catalog_bundle_stock_operations.organization_id', currentOrganizationId());
    }
}
