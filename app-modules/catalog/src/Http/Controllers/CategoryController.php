<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\CategoryDTO;
use Lahatre\Catalog\DTO\CategoryFilterDTO;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Services\CategoryService;

class CategoryController
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('list', Category::class);

        $filters = CategoryFilterDTO::fromRequest($request);

        $response = $this->categoryService->list($filters);

        return ApiResponse::success($response);
    }

    public function show(Category $category): JsonResponse
    {
        Gate::authorize('retrieve', $category);

        $response = $this->categoryService->retrieve($category);

        return ApiResponse::success($response);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $dto = CategoryDTO::fromRequest($request);

        $response = $this->categoryService->create($dto);

        return ApiResponse::created($response);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        Gate::authorize('update', $category);

        $dto = CategoryDTO::forUpdate($request, $category);

        $response = $this->categoryService->update($category, $dto);

        return ApiResponse::success($response);
    }

    public function destroy(Category $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        $this->categoryService->delete($category);

        return ApiResponse::noContent();
    }
}
