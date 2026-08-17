<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Lahatre\Catalog\Database\Factories\ProductCategoryFactory;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $product_id
 * @property string $category_id
 * @property-read Category $category
 * @property-read Product $product
 *
 * @method static Builder<static>|ProductCategory newModelQuery()
 * @method static Builder<static>|ProductCategory newQuery()
 * @method static Builder<static>|ProductCategory query()
 * @method static Builder<static>|ProductCategory whereCategoryId($value)
 * @method static Builder<static>|ProductCategory whereId($value)
 * @method static Builder<static>|ProductCategory whereProductId($value)
 * @method static ProductCategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|ProductCategory whereOrganizationId($value)
 *
 * @mixin \Eloquent
 */
class ProductCategory extends Pivot
{
    use SharedTraits;

    public $timestamps = false;

    protected $table = 'catalog_product_categories';

    protected $fillable = [
        'organization_id',
        'product_id',
        'category_id',
    ];

    public $incrementing = false;

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'product_id'      => 'string',
        'category_id'     => 'string',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id')
            ->where('catalog_products.organization_id', currentOrganizationId());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id')
            ->where('catalog_categories.organization_id', currentOrganizationId());
    }
}
