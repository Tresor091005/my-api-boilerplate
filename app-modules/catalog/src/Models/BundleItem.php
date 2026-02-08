<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $item_type
 * @property string $item_id
 * @property string $bundle_id
 * @property int $quantity
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Bundle $bundle
 * @property-read Model|\Eloquent $item
 *
 * @method static Builder<static>|BundleItem newModelQuery()
 * @method static Builder<static>|BundleItem newQuery()
 * @method static Builder<static>|BundleItem query()
 * @method static Builder<static>|BundleItem whereBundleId($value)
 * @method static Builder<static>|BundleItem whereCreatedAt($value)
 * @method static Builder<static>|BundleItem whereId($value)
 * @method static Builder<static>|BundleItem whereItemId($value)
 * @method static Builder<static>|BundleItem whereItemType($value)
 * @method static Builder<static>|BundleItem whereQuantity($value)
 * @method static Builder<static>|BundleItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BundleItem extends Model
{
    use SharedTraits;

    protected $table = 'catalog_bundle_items';

    protected $fillable = [
        'item_type',
        'item_id',
        'bundle_id',
        'quantity',
    ];

    protected $casts = [
        'id'         => 'string',
        'item_type'  => 'string',
        'item_id'    => 'string',
        'bundle_id'  => 'string',
        'quantity'   => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function item(): MorphTo
    {
        return $this->morphTo('item', 'item_type', 'item_id');
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class, 'bundle_id', 'id');
    }
}
