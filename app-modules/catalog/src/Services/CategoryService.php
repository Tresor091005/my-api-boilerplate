<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\CategoryAssertion;
use Lahatre\Catalog\Data\CategoryData;
use Lahatre\Catalog\Data\CategoryFilterData;
use Lahatre\Catalog\Http\Resources\CategoryCollection;
use Lahatre\Catalog\Http\Resources\CategoryResource;
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

    public function list(CategoryFilterData $filters): CategoryCollection
    {
        $query = Category::query()->where('organization_id', getPermissionsTeamId());

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

        $categories = stableCursorPaginate($query, $filters);

        return CategoryCollection::make($categories);
    }

    public function retrieve(Category $category): CategoryResource
    {
        $category->load(['bloodline']);

        return CategoryResource::make($category);
    }

    public function create(CategoryData $data): CategoryResource
    {
        $category = new Category();

        $category->fill([
            'organization_id' => getPermissionsTeamId(),
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

        return CategoryResource::make($category->load(['bloodline']));
    }

    public function update(Category $category, CategoryData $data): CategoryResource
    {
        if (!$data->parentId instanceof MissingValue) {
            $this->categoryAssertion->assertCanBeNewParent($category, $data->parentId);
        }

        $category->fill(withoutMissing([
            'name'      => $data->name,
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]));

        DB::transaction(fn () => $category->save());

        return CategoryResource::make($category->load(['bloodline']));
    }

    public function delete(Category $category): void
    {
        $this->categoryAssertion->assertCanDelete($category);

        DB::transaction(fn () => $category->delete());
    }
}
