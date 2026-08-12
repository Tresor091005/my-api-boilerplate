<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\Category;
use Lahatre\Shared\Exceptions\AssertionException;

final class CategoryException extends AssertionException
{
    public static function hasChildren(Category $category): self
    {
        return new self(
            __('catalog::exceptions.category_has_children'),
            ['category_id' => $category->id]
        );
    }

    public static function cannotBeDescendantParent(Category $category, string $newParentId): self
    {
        return new self(
            __('catalog::exceptions.category_cannot_be_descendant_parent'),
            [
                'category_id'   => $category->id,
                'new_parent_id' => $newParentId,
            ]
        );
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
