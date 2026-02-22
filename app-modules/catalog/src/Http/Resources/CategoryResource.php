<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
                fn (): array => $this->buildTree($this->bloodline)
            ),
        ];
    }

    private function buildTree(iterable $items): array
    {
        $map = [];

        // 1. Index each item by its ID
        foreach ($items as $item) {
            $map[$item->id] = [
                'id'         => $item->id,
                'handle'     => $item->handle,
                'name'       => $item->name,
                'depth'      => $item->depth,
                'is_active'  => $item->is_active,
                'children'   => [],
                '_parent_id' => $item->parent_id,
            ];
        }

        // 2. Attach each node to its parent (by reference)
        $roots = [];

        foreach ($map as $id => &$node) {
            $parentId = $node['_parent_id'];
            unset($node['_parent_id']);

            if ($parentId && isset($map[$parentId])) {
                $map[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }

        unset($node);

        return $roots;
    }
}
