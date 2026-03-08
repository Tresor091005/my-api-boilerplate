<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions\Category;

use Lahatre\Catalog\Models\Category;
use Lahatre\Shared\Exceptions\AssertionException;

class CategoryCannotBeDescendantParentException extends AssertionException
{
    public function __construct(Category $category, string $newParentId)
    {
        parent::__construct(
            __('catalog::exceptions.category_cannot_be_descendant_parent'),
            [
                'category_id'   => $category->id,
                'new_parent_id' => $newParentId,
            ]
        );
    }
}
