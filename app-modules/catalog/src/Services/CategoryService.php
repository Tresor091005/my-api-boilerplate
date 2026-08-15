<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\CategoryAssertion;
use Lahatre\Catalog\Data\CategoryData;
use Lahatre\Catalog\Data\CategoryFilterData;
use Lahatre\Catalog\Exceptions\CategoryException;
use Lahatre\Catalog\Models\Category;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

use Lahatre\Shared\Support\HandleGenerator;

class CategoryService
{
    public function __construct(
        protected CategoryAssertion $categoryAssertion
    ) {}

    public function paginate(CategoryFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            applyResponseContextToQuery($this->categoriesQuery($filters)),
            $filters,
        );
    }

    /** @return Builder<Category> */
    private function categoriesQuery(CategoryFilterData $filters): Builder
    {
        $query = Category::query()->where('organization_id', currentOrganizationId());

        if ($filters->handle) {
            $query->where('handle', 'like', "$filters->handle%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "$filters->name%");
        }
        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }
        if ($filters->parentId) {
            $query->where('parent_id', $filters->parentId);
        }

        return $query;
    }

    public function retrieve(Category $category): Category
    {
        $category->load(responseRelationsToLoad());

        return $category;
    }

    public function create(CategoryData $data): Category
    {
        $category = new Category;

        $category->fill([
            'organization_id' => currentOrganizationId(),
            'name'            => required($data->name),
            'parent_id'       => required($data->parentId),
            'is_active'       => required($data->isActive),
        ]);

        $category->handle = HandleGenerator::generate(
            required($data->name),
            $category->getTable(),
            extra: ['organization_id' => $category->organization_id]
        );

        DB::transaction(fn () => $category->save());

        return $category->load(responseRelationsToLoad());
    }

    public function update(Category $category, CategoryData $data): Category
    {
        if (!$data->parentId instanceof MissingValue) {
            $newParent = $data->parentId === null
                ? null
                : Category::query()
                    ->where('organization_id', currentOrganizationId())
                    ->whereNull('deleted_at')
                    ->find($data->parentId);

            if ($data->parentId !== null && $newParent === null) {
                throw CategoryException::parentNotFound($data->parentId);
            }

            $this->categoryAssertion->assertCanBeNewParent($category, $newParent);
        }

        $category->fill(withoutMissing([
            'name'      => $data->name,
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]));

        DB::transaction(fn () => $category->save());

        return $category->load(responseRelationsToLoad());
    }

    public function delete(Category $category): void
    {
        $this->categoryAssertion->assertCanDelete($category);

        DB::transaction(fn () => $category->delete());
    }
}
