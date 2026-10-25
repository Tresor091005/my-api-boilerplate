<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\ProductData;
use Lahatre\Catalog\Data\ProductFilterData;
use Lahatre\Catalog\Http\Requests\ProductFilterRequest;
use Lahatre\Catalog\Http\Requests\ProductRequest;
use Lahatre\Catalog\Http\Resources\ProductResource;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Services\ProductService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class ProductController
{
    public function __construct(
        protected ProductService $productService,
    ) {}

    public function index(ProductFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Product::class);

        $filters = ProductFilterData::fromArray($request->validated());

        $response = $this->productService->paginate($filters);

        return ApiResponse::cursorPage($response, ProductResource::class);
    }

    public function show(Product $product): JsonResponse|Response
    {
        Gate::authorize('retrieve', $product);

        $response = $this->productService->retrieve($product);

        return ApiResponse::item($response, ProductResource::class);
    }

    public function store(ProductRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Product::class);

        $data = ProductData::fromArray($request->validated());

        $response = $this->productService->create($data);

        return ApiResponse::item($response, ProductResource::class, 201);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse|Response
    {
        Gate::authorize('update', $product);

        $data = ProductData::fromArray(
            $request->validated(),
            missingFields: ['name', 'description', 'categories', 'variants'],
        );

        $response = $this->productService->update($product, $data);

        return ApiResponse::item($response, ProductResource::class);
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);

        $this->productService->delete($product);

        return ApiResponse::noContent();
    }
}
