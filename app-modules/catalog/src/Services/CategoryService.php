<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Catalog\Assertions\CategoryAssertion;
use Lahatre\Catalog\DTO\CategoryDTO;
use Lahatre\Catalog\DTO\CategoryFilterDTO;
use Lahatre\Catalog\Http\Resources\CategoryCollection;
use Lahatre\Catalog\Http\Resources\CategoryResource;
use Lahatre\Catalog\Models\Category;
use Lahatre\Shared\Support\HandleGenerator;

class CategoryService
{
    public function __construct(
        protected CategoryAssertion $categoryAssertion
    ) {}

    public function list(CategoryFilterDTO $filters): CategoryCollection
    {
        $query = Category::query();

        if ($filters->handle) {
            $query->where('handle', 'like', "%{$filters->handle}%");
        }
        if ($filters->name) {
            $query->where('name', 'like', "%{$filters->name}%");
        }
        if ($filters->is_active !== null) {
            $query->where('is_active', $filters->is_active);
        }
        if ($filters->parent_id) {
            $query->where('parent_id', $filters->parent_id);
        }

        $query->orderBy($filters->sort_by, $filters->sort_order);

        $categories = $filters->cursor
            ? $query->cursorPaginate($filters->per_page, ['*'], 'cursor', $filters->cursor)
            : $query->cursorPaginate($filters->per_page);

        return CategoryCollection::make($categories);
    }

    public function retrieve(Category $category): CategoryResource
    {
        $category->load(['bloodline']);

        return CategoryResource::make($category);
    }

    public function create(CategoryDTO $dto): CategoryResource
    {
        $category = new Category();

        $category->fill([
            'name'      => $dto->name,
            'parent_id' => $dto->parent_id,
            'is_active' => $dto->is_active,
        ]);

        $category->handle = HandleGenerator::generate(
            $dto->name,
            $category->getTable()
        );

        DB::transaction(fn () => $category->save());

        return CategoryResource::make($category->load(['bloodline']));
    }

    public function update(Category $category, CategoryDTO $dto): CategoryResource
    {
        $this->categoryAssertion->assertCanBeNewParent($category, $dto->parent_id);

        $category->fill([
            'name'      => $dto->name,
            'parent_id' => $dto->parent_id,
            'is_active' => $dto->is_active,
        ]);

        DB::transaction(fn () => $category->save());

        return CategoryResource::make($category->load(['bloodline']));
    }

    public function delete(Category $category): void
    {
        $this->categoryAssertion->assertCanDelete($category);

        DB::transaction(function () use ($category): void {
            $category->products()->sync([]);
            $category->delete();
        });
    }
}
