<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\CategoryDTO;
use Lahatre\Catalog\DTO\CategoryFilterDTO;
use Lahatre\Catalog\Http\Resources\CategoryCollection;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Services\CategoryService;

class CategoryController
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index(Request $request): CategoryCollection
    {
        Gate::authorize('list', Category::class);

        $filters = CategoryFilterDTO::fromRequest($request);

        return $this->categoryService->list($filters);
    }

    public function show(Category $category): JsonResponse
    {
        Gate::authorize('retrieve', $category);

        $response = $this->categoryService->retrieve($category);

        return response()->json($response);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $dto = CategoryDTO::fromRequest($request);

        $response = $this->categoryService->create($dto);

        return response()->json($response, 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        Gate::authorize('update', $category);

        $dto = CategoryDTO::forUpdate($request, $category);

        $response = $this->categoryService->update($category, $dto);

        return response()->json($response);
    }

    public function destroy(Category $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        $this->categoryService->delete($category);

        return response()->json(null, 204);
    }

    public function products(Category $category): JsonResponse
    {
        Gate::authorize('viewProducts', $category);

        $response = $this->categoryService->products($category);

        return response()->json($response);
    }
}
