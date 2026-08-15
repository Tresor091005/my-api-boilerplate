<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\CategoryData;
use Lahatre\Catalog\Data\CategoryFilterData;
use Lahatre\Catalog\Http\Requests\CategoryFilterRequest;
use Lahatre\Catalog\Http\Requests\CategoryRequest;
use Lahatre\Catalog\Http\Resources\CategoryCollection;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Services\CategoryService;

class CategoryController
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index(CategoryFilterRequest $request): CategoryCollection
    {
        Gate::authorize('list', Category::class);

        $filters = CategoryFilterData::fromArray($request->validated());

        return $this->categoryService->list($filters);
    }

    public function show(Category $category): JsonResponse
    {
        Gate::authorize('retrieve', $category);

        $response = $this->categoryService->retrieve($category);

        return response()->json($response);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $data = CategoryData::fromArray($request->validated());

        $response = $this->categoryService->create($data);

        return response()->json($response, 201);
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        Gate::authorize('update', $category);

        $data = CategoryData::fromArray(
            $request->validated(),
            missingFields: ['name', 'parent_id', 'is_active'],
        );

        $response = $this->categoryService->update($category, $data);

        return response()->json($response);
    }

    public function destroy(Category $category): Response
    {
        Gate::authorize('delete', $category);

        $this->categoryService->delete($category);

        return response()->noContent();
    }
}
