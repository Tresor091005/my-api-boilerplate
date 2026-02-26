<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\ProductDTO;
use Lahatre\Catalog\DTO\ProductFilterDTO;
use Lahatre\Catalog\Http\Resources\ProductCollection;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Services\ProductService;

class ProductController
{
    public function __construct(
        protected ProductService $productService
    ) {}

    public function index(Request $request): ProductCollection
    {
        Gate::authorize('list', Product::class);

        $filters = ProductFilterDTO::fromRequest($request);

        return $this->productService->list($filters);
    }

    public function show(Product $product): JsonResponse
    {
        Gate::authorize('retrieve', $product);

        $response = $this->productService->retrieve($product);

        return response()->json($response);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $dto = ProductDTO::fromRequest($request);

        $response = $this->productService->create($dto);

        return response()->json($response, 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('update', $product);

        $dto = ProductDTO::forUpdate($request, $product);

        $response = $this->productService->update($product, $dto);

        return response()->json($response);
    }

    public function destroy(Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);

        $this->productService->delete($product);

        return response()->json(null, 204);
    }
}
