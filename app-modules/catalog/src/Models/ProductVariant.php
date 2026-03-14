<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $product_id
 * @property string $sku
 * @property string|null $unit_group_id
 * @property bool $manage_stock
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $name
 * @property-read string $options_label
 * @property-read VariantOptionValue|null $pivot
 * @property-read Collection<int, OptionValue> $optionValues
 * @property-read int|null $option_values_count
 * @property-read Product $product
 * @property-read UnitGroup|null $unitGroup
 * @property-read Collection<int, Price> $prices
 * @property-read int|null $prices_count
 *
 * @method static Builder<static>|ProductVariant newModelQuery()
 * @method static Builder<static>|ProductVariant newQuery()
 * @method static Builder<static>|ProductVariant query()
 * @method static Builder<static>|ProductVariant whereCreatedAt($value)
 * @method static Builder<static>|ProductVariant whereId($value)
 * @method static Builder<static>|ProductVariant whereIsActive($value)
 * @method static Builder<static>|ProductVariant whereManageStock($value)
 * @method static Builder<static>|ProductVariant whereProductId($value)
 * @method static Builder<static>|ProductVariant whereSku($value)
 * @method static Builder<static>|ProductVariant whereUnitGroupId($value)
 * @method static Builder<static>|ProductVariant whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ProductVariant extends Model
{
    use SharedTraits;

    protected $table = 'catalog_product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'unit_group_id',
        'manage_stock',
        'is_active',
    ];

    protected $casts = [
        'id'            => 'string',
        'product_id'    => 'string',
        'sku'           => 'string',
        'unit_group_id' => 'string',
        'manage_stock'  => 'boolean',
        'is_active'     => 'boolean',
        'created_at'    => 'immutable_datetime',
        'updated_at'    => 'immutable_datetime',
    ];

    protected function optionsLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->optionValues
                ->map(fn (OptionValue $optionValue): string => "[{$optionValue->option->name}: {$optionValue->value}]")
                ->implode(' ')
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $label = $this->options_label;

                return $label !== ''
                    ? "{$this->product->name} {$label}"
                    : $this->product->name;
            }
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function unitGroup(): BelongsTo
    {
        return $this->belongsTo(UnitGroup::class, 'unit_group_id', 'id');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            OptionValue::class,
            'catalog_variant_option_value',
            'variant_id',
            'option_value_id'
        )->using(VariantOptionValue::class);
    }

    public function prices(): MorphMany
    {
        return $this->morphMany(Price::class, 'priceable');
    }
}
