<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lahatre\Catalog\Models\Category;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class CategoryResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('handle')
            ->field('name')
            ->field('is_active')
            ->field('parent_id')
            ->field('created_at')
            ->field('updated_at')
            // the whole tree around the category: rendered only when asked for
            ->field('bloodline', relations: ['bloodline'], computedBy: fn (Model $category): array => self::formatTree($category->getRelation('bloodline')->toTree()), default: false);
    }

    /**
     * @param  Collection<int, Category>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private static function formatTree(Collection $nodes): array
    {
        return $nodes->map(function (Category $node): array {
            $data = [
                'id'        => $node->id,
                'handle'    => $node->handle,
                'name'      => $node->name,
                'depth'     => $node->depth,
                'is_active' => $node->is_active,
                'children'  => [],
            ];

            // toTree() fills `children` in memory from the loaded bloodline: no query here
            if ($node->relationLoaded('children') && $node->children->isNotEmpty()) {
                $data['children'] = self::formatTree($node->children);
            }

            return $data;
        })->all();
    }
}
