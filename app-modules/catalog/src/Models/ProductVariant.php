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
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\ProductVariantFactory;
use Lahatre\Catalog\Enums\CatalogItemType;
use Lahatre\Master\Models\Label;
use Lahatre\Master\Traits\InteractsWithLabels;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $product_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read string $name
 * @property-read string $options_label
 * @property-read VariantOptionValue|null $pivot
 * @property-read Collection<int, OptionValue> $optionValues
 * @property-read int|null $option_values_count
 * @property-read Product $product
 * @property-read CatalogItem $catalogItem
 * @property-read Collection<int, Label> $labels
 * @property-read int|null $labels_count
 *
 * @method static Builder<static>|ProductVariant newModelQuery()
 * @method static Builder<static>|ProductVariant newQuery()
 * @method static Builder<static>|ProductVariant query()
 * @method static Builder<static>|ProductVariant whereCreatedAt($value)
 * @method static Builder<static>|ProductVariant whereId($value)
 * @method static Builder<static>|ProductVariant whereProductId($value)
 * @method static Builder<static>|ProductVariant whereUpdatedAt($value)
 * @method static ProductVariantFactory factory($count = null, $state = [])
 * @method static Builder<static>|ProductVariant onlyTrashed()
 * @method static Builder<static>|ProductVariant whereOrganizationId($value)
 * @method static Builder<static>|ProductVariant withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ProductVariant withoutTrashed()
 * @method static Builder<static>|ProductVariant whereDeletedAt($value)
 * @method static Builder<static>|ProductVariant toLabelOrganization()
 * @method static Builder<static>|ProductVariant withAllLabelsOfGroup(string $group, array $labels)
 * @method static Builder<static>|ProductVariant withAnyLabelsOfGroup(string $group, array $labels)
 * @method static Builder<static>|ProductVariant withoutLabelsOfGroup(string $group, array $labels)
 *
 * @mixin \Eloquent
 */
class ProductVariant extends Model
{
    use InteractsWithLabels;
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_product_variants';

    protected $fillable = [
        'organization_id',
        'product_id',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'product_id'      => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
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
        return $this->belongsTo(Product::class, 'product_id', 'id')
            ->where('catalog_products.organization_id', currentOrganizationId());
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'id', 'id')
            ->where('catalog_items.item_type', CatalogItemType::ProductVariant->value)
            ->where('catalog_items.organization_id', currentOrganizationId());
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            OptionValue::class,
            'catalog_variant_option_value',
            'variant_id',
            'option_value_id'
        )->using(VariantOptionValue::class)
            ->withPivot('organization_id')
            ->wherePivot('organization_id', currentOrganizationId())
            ->where('catalog_option_values.organization_id', currentOrganizationId());
    }
}
