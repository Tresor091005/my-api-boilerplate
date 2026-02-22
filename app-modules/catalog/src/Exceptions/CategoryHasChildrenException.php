<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Catalog\Models\Category;
use Lahatre\Shared\Exceptions\AssertionException;

class CategoryHasChildrenException extends AssertionException
{
    public function __construct(Category $category)
    {
        parent::__construct(
            __('catalog::exceptions.category_has_children'),
            ['category_id' => $category->id]
        );
    }
}
