<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lahatre\Catalog\Database\Factories\CategoryFactory;
use Lahatre\Shared\Traits\SharedTraits;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $handle
 * @property string $name
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string|null $parent_id
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Category> $children
 * @property-read int|null $children_count
 * @property-read Category|null $parent
 * @property-read ProductCategory|null $pivot
 * @property-read Collection<int, Product> $products
 * @property-read int|null $products_count
 * @property-read int $depth
 * @property-read string $path
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, Category> $ancestors The model's recursive parents.
 * @property-read int|null $ancestors_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, Category> $ancestorsAndSelf The model's recursive parents and itself.
 * @property-read int|null $ancestors_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, Category> $bloodline The model's ancestors, descendants and itself.
 * @property-read int|null $bloodline_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, Category> $childrenAndSelf The model's direct children and itself.
 * @property-read int|null $children_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, Category> $descendants The model's recursive children.
 * @property-read int|null $descendants_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, Category> $descendantsAndSelf The model's recursive children and itself.
 * @property-read int|null $descendants_and_self_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, Category> $parentAndSelf The model's direct parent and itself.
 * @property-read int|null $parent_and_self_count
 * @property-read Category|null $rootAncestor The model's topmost parent.
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, Category> $siblings The parent's other children.
 * @property-read int|null $siblings_count
 * @property-read \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, Category> $siblingsAndSelf All the parent's children.
 * @property-read int|null $siblings_and_self_count
 *
 * @method static Builder<static>|Category newModelQuery()
 * @method static Builder<static>|Category newQuery()
 * @method static Builder<static>|Category query()
 * @method static Builder<static>|Category whereCreatedAt($value)
 * @method static Builder<static>|Category whereHandle($value)
 * @method static Builder<static>|Category whereId($value)
 * @method static Builder<static>|Category whereIsActive($value)
 * @method static Builder<static>|Category whereName($value)
 * @method static Builder<static>|Category whereParentId($value)
 * @method static Builder<static>|Category whereUpdatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> all($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category breadthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category depthFirst()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category doesntHaveChildren()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Collection<int, static> get($columns = ['*'])
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category getExpressionGrammar()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category hasChildren()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category hasParent()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category isLeaf()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category isRoot()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category tree($maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category treeOf((Model|callable) $constraint, $maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereDepth($operator, $value = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category withGlobalScopes(array $scopes)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category withRelationshipExpression($direction, callable $constraint, $initialDepth, $from = null, $maxDepth = null)
 * @method static CategoryFactory factory($count = null, $state = [])
 * @method static Builder<static>|Category onlyTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereOrganizationId($value)
 * @method static Builder<static>|Category withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Category withoutTrashed()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereDeletedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category newQuery()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category query()
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category treeOf(Model|callable $constraint, $maxDepth = null)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereHandle($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereIsActive($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereParentId($value)
 * @method static \Staudenmeir\LaravelAdjacencyList\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Category extends Model
{
    use HasRecursiveRelationships;
    use SharedTraits;
    use SoftDeletes;

    protected $table = 'catalog_categories';

    protected $fillable = [
        'organization_id',
        'name',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'id'              => 'string',
        'organization_id' => 'string',
        'handle'          => 'string',
        'name'            => 'string',
        'parent_id'       => 'string',
        'is_active'       => 'boolean',
        'created_at'      => 'immutable_datetime',
        'updated_at'      => 'immutable_datetime',
        'deleted_at'      => 'immutable_datetime',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'catalog_product_categories', 'category_id', 'product_id')
            ->using(ProductCategory::class)
            ->withPivot('organization_id')
            ->wherePivot('organization_id', currentOrganizationId())
            ->where('catalog_products.organization_id', currentOrganizationId());
    }
}
