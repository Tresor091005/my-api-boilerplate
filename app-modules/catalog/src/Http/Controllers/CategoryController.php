<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\CategoryData;
use Lahatre\Catalog\Data\CategoryFilterData;
use Lahatre\Catalog\Http\Requests\CategoryFilterRequest;
use Lahatre\Catalog\Http\Requests\CategoryRequest;
use Lahatre\Catalog\Http\Resources\CategoryCollection;
use Lahatre\Catalog\Http\Resources\CategoryResource;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Services\CategoryService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class CategoryController
{
    public function __construct(
        protected CategoryService $categoryService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(CategoryFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Category::class);

        $filters = CategoryFilterData::fromArray($request->validated());

        $response = $this->categoryService->paginate($filters);

        return $this->responseResponder->respond(fn (): JsonResource => CategoryCollection::make($response));
    }

    public function show(Category $category): JsonResponse|Response
    {
        Gate::authorize('retrieve', $category);

        $response = $this->categoryService->retrieve($category);

        return $this->responseResponder->respond(fn (): JsonResource => CategoryResource::make($response));
    }

    public function store(CategoryRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Category::class);

        $data = CategoryData::fromArray($request->validated());

        $response = $this->categoryService->create($data);

        return $this->responseResponder->respond(
            fn (): JsonResource => CategoryResource::make($response),
            status: 201,
        );
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse|Response
    {
        Gate::authorize('update', $category);

        $data = CategoryData::fromArray(
            $request->validated(),
            missingFields: ['name', 'parent_id', 'is_active'],
        );

        $response = $this->categoryService->update($category, $data);

        return $this->responseResponder->respond(fn (): JsonResource => CategoryResource::make($response));
    }

    public function destroy(Category $category): Response
    {
        Gate::authorize('delete', $category);

        $this->categoryService->delete($category);

        return response()->noContent();
    }
}
