<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\Category\CategoryCannotBeDescendantParentException;
use Lahatre\Catalog\Exceptions\Category\CategoryHasChildrenException;
use Lahatre\Catalog\Models\Category;

class CategoryAssertion
{
    /**
     * Asserts that a category can be deleted.
     * A category can only be deleted if it does not have any children.
     *
     * @throws CategoryHasChildrenException If the category has children.
     */
    public function assertCanDelete(Category $category): void
    {
        if ($category->children()->exists()) {
            throw new CategoryHasChildrenException($category);
        }
    }

    /**
     * Asserts that a new parent ID is valid for the given category.
     * A new parent ID is valid if it is null (for a top-level category),
     * or if it refers to a category that is not itself or one of its descendants.
     *
     * @throws CategoryCannotBeDescendantParentException If the new parent is the category itself or one of its descendants.
     */
    public function assertCanBeNewParent(Category $category, ?string $newParentId): void
    {
        if ($newParentId === null) {
            return;
        }

        if ($newParentId === $category->id) {
            throw new CategoryCannotBeDescendantParentException($category, $newParentId);
        }

        $descendantIds = $category->descendants()->pluck('id')->toArray();

        if (in_array($newParentId, $descendantIds, true)) {
            throw new CategoryCannotBeDescendantParentException($category, $newParentId);
        }
    }
}
