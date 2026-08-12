<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\CategoryException;
use Lahatre\Catalog\Models\Category;

class CategoryAssertion
{
    /**
     * Asserts that a category can be deleted.
     * A category can only be deleted if it does not have any children.
     *
     * @throws CategoryException If the category has children.
     */
    public function assertCanDelete(Category $category): void
    {
        if ($category->children()->whereNull('deleted_at')->exists()) {
            throw CategoryException::hasChildren($category);
        }
    }

    /**
     * Asserts that a new parent is valid for the given category.
     * A new parent is valid if it is null (for a top-level category),
     * or if it refers to a category that is not itself or one of its descendants.
     *
     * @throws CategoryException If the new parent is the category itself or one of its descendants.
     */
    public function assertCanBeNewParent(Category $category, ?Category $newParent): void
    {
        if ($newParent === null) {
            return;
        }

        if ((string) $newParent->getKey() === (string) $category->getKey()) {
            throw CategoryException::cannotBeDescendantParent($category, (string) $newParent->getKey());
        }

        $descendantIds = $category->descendants()->pluck('id')->toArray();

        if (in_array($newParent->getKey(), $descendantIds, true)) {
            throw CategoryException::cannotBeDescendantParent($category, (string) $newParent->getKey());
        }
    }
}
