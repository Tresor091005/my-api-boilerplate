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
 * @property string $variant_id
 * @property string $option_value_id
 * @property string $option_id
 * @property-read Option $option
 * @property-read OptionValue $optionValue
 * @property-read Product $product
 * @property-read ProductVariant $variant
 *
 * @method static Builder<static>|VariantOptionValue newModelQuery()
 * @method static Builder<static>|VariantOptionValue newQuery()
 * @method static Builder<static>|VariantOptionValue query()
 * @method static Builder<static>|VariantOptionValue whereId($value)
 * @method static Builder<static>|VariantOptionValue whereOptionId($value)
 * @method static Builder<static>|VariantOptionValue whereOptionValueId($value)
 * @method static Builder<static>|VariantOptionValue whereProductId($value)
 * @method static Builder<static>|VariantOptionValue whereVariantId($value)
 *
 * @mixin \Eloquent
 */
class VariantOptionValue extends Pivot
{
    use SharedTraits;

    protected $table = 'catalog_variant_option_value';

    protected $casts = [
        'id'              => 'string',
        'variant_id'      => 'string',
        'option_value_id' => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id', 'id');
    }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(OptionValue::class, 'option_value_id', 'id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id', 'id');
    }
}
