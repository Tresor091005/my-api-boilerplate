<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $product_id
 * @property string $tag_id
 * @property-read Product $product
 * @property-read Tag $tag
 *
 * @method static Builder<static>|ProductTag newModelQuery()
 * @method static Builder<static>|ProductTag newQuery()
 * @method static Builder<static>|ProductTag query()
 * @method static Builder<static>|ProductTag whereId($value)
 * @method static Builder<static>|ProductTag whereProductId($value)
 * @method static Builder<static>|ProductTag whereTagId($value)
 *
 * @mixin \Eloquent
 */
class ProductTag extends Pivot
{
    use SharedTraits;

    protected $table = 'catalog_product_tags';

    protected $casts = [
        'id'         => 'string',
        'product_id' => 'string',
        'tag_id'     => 'string',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id', 'id');
    }
}
