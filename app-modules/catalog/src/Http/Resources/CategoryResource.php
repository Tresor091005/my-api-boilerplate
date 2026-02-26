<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Lahatre\Catalog\Models\Category;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'handle'     => $this->handle,
            'name'       => $this->name,
            'is_active'  => $this->is_active,
            'parent_id'  => $this->parent_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'bloodline'  => $this->whenLoaded('bloodline',
                fn (): array => $this->formatTree($this->bloodline->toTree())
            ),
        ];
    }

    /**
     * Recursively formats a tree of categories.
     *
     * @param  Collection<int, Category>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function formatTree(Collection $nodes): array
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

            if ($node->relationLoaded('children') && $node->children->isNotEmpty()) {
                $data['children'] = $this->formatTree($node->children);
            }

            return $data;
        })->all();
    }
}
