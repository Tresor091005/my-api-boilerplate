<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\ProductData;
use Lahatre\Catalog\Data\ProductFilterData;
use Lahatre\Catalog\Http\Requests\ProductFilterRequest;
use Lahatre\Catalog\Http\Requests\ProductRequest;
use Lahatre\Catalog\Http\Resources\ProductCollection;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Services\ProductService;

class ProductController
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function index(ProductFilterRequest $request): ProductCollection
    {
        Gate::authorize('list', Product::class);

        $filters = ProductFilterData::fromArray($request->validated());

        return $this->productService->list($filters);
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('retrieve', $product);

        $response = $this->productService->retrieve($product);

        return response()->json($response);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $data = ProductData::fromArray($request->validated());

        $response = $this->productService->create($data);

        return response()->json($response, 201);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $data = ProductData::fromArray(
            $request->validated(),
            missingFields: ['name', 'description', 'is_active', 'categories', 'variants'],
        );

        $response = $this->productService->update($product, $data);

        return response()->json($response);
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);

        $this->productService->delete($product);

        return response()->noContent();
    }
}
