<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\ProductFactory;
use Lahatre\Library\Contracts\HasFileSlots;
use Lahatre\Library\Models\FileAttachment;
use Lahatre\Library\Traits\InteractsWithFile;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $handle
 * @property string $name
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read VariantOptionValue|ProductCategory|null $pivot
 * @property-read Collection<int, Category> $categories
 * @property-read int|null $categories_count
 * @property-read Collection<int, OptionValue> $optionValues
 * @property-read int|null $option_values_count
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read int|null $variants_count
 *
 * @method static Builder<static>|Product newModelQuery()
 * @method static Builder<static>|Product newQuery()
 * @method static Builder<static>|Product query()
 * @method static Builder<static>|Product whereCreatedAt($value)
 * @method static Builder<static>|Product whereDescription($value)
 * @method static Builder<static>|Product whereHandle($value)
 * @method static Builder<static>|Product whereId($value)
 * @method static Builder<static>|Product whereName($value)
 * @method static Builder<static>|Product whereUpdatedAt($value)
 * @method static ProductFactory factory($count = null, $state = [])
 * @method static Builder<static>|Product onlyTrashed()
 * @method static Builder<static>|Product whereOrganizationId($value)
 * @method static Builder<static>|Product withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Product withoutTrashed()
 * @method static Builder<static>|Product whereDeletedAt($value)
 *
 * @mixin \Eloquent
 */
class Product extends Model implements HasFileSlots
{
    use InteractsWithFile;
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_products';

    protected $fillable = [
        'organization_id',
        'handle',
        'name',
        'description',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'handle'          => 'string',
        'name'            => 'string',
        'description'     => 'string',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    /** @return array<string, array{max_files: int, mime_types: list<string>}> */
    public function fileSlots(): array
    {
        $images = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        return [
            'main'    => ['max_files' => 1, 'mime_types' => $images],
            'gallery' => ['max_files' => 20, 'mime_types' => $images],
        ];
    }

    /** @return MorphMany<FileAttachment, $this> */
    public function mainFileAttachments(): MorphMany
    {
        return $this->fileAttachmentsForSlot('main');
    }

    /** @return MorphMany<FileAttachment, $this> */
    public function galleryFileAttachments(): MorphMany
    {
        return $this->fileAttachmentsForSlot('gallery');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'catalog_product_categories', 'product_id', 'category_id')
            ->using(ProductCategory::class)
            ->withPivot('organization_id')
            ->wherePivot('organization_id', currentOrganizationId())
            ->where('catalog_categories.organization_id', currentOrganizationId());
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'id')
            ->where('catalog_product_variants.organization_id', currentOrganizationId());
    }

    public function optionValues(): BelongsToMany
    {
        // Note: relation optionValues instead of options directly
        // because we don't want to show non used values
        return $this->belongsToMany(
            OptionValue::class,
            'catalog_variant_option_value',
            'product_id',
            'option_value_id'
        )->distinct()
            ->using(VariantOptionValue::class)
            ->withPivot('organization_id')
            ->wherePivot('organization_id', currentOrganizationId())
            ->where('catalog_option_values.organization_id', currentOrganizationId());
    }
}
