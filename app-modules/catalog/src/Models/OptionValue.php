<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\OptionValueFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $option_id
 * @property string $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Option $option
 * @property-read VariantOptionValue|null $pivot
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read int|null $variants_count
 *
 * @method static Builder<static>|OptionValue newModelQuery()
 * @method static Builder<static>|OptionValue newQuery()
 * @method static Builder<static>|OptionValue query()
 * @method static Builder<static>|OptionValue whereCreatedAt($value)
 * @method static Builder<static>|OptionValue whereId($value)
 * @method static Builder<static>|OptionValue whereOptionId($value)
 * @method static Builder<static>|OptionValue whereUpdatedAt($value)
 * @method static Builder<static>|OptionValue whereValue($value)
 * @method static OptionValueFactory factory($count = null, $state = [])
 * @method static Builder<static>|OptionValue onlyTrashed()
 * @method static Builder<static>|OptionValue whereOrganizationId($value)
 * @method static Builder<static>|OptionValue withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|OptionValue withoutTrashed()
 *
 * @property CarbonImmutable|null $deleted_at
 *
 * @method static Builder<static>|OptionValue whereDeletedAt($value)
 *
 * @mixin \Eloquent
 */
class OptionValue extends Model
{
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_option_values';

    protected $fillable = [
        'organization_id',
        'option_id',
        'value',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'option_id'       => 'string',
        'value'           => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id', 'id')
            ->where('catalog_options.organization_id', currentOrganizationId());
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'catalog_variant_option_value',
            'option_value_id',
            'variant_id'
        )->using(VariantOptionValue::class)
            ->withPivot('organization_id')
            ->wherePivot('organization_id', currentOrganizationId())
            ->where('catalog_product_variants.organization_id', currentOrganizationId());
    }
}
