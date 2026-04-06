<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\ProductVariantDTO;
use Lahatre\Catalog\DTO\ProductVariantFilterDTO;
use Lahatre\Catalog\DTO\ProductVariantUpdateDTO;
use Lahatre\Catalog\Http\Resources\ProductVariantCollection;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\ProductVariantService;

class ProductVariantController
{
    public function __construct(
        protected ProductVariantService $productVariantService
    ) {}

    public function index(Request $request, Product $product): ProductVariantCollection
    {
        Gate::authorize('list', ProductVariant::class);

        $filters = ProductVariantFilterDTO::fromRequest($request);

        return $this->productVariantService->list($product, $filters);
    }

    public function show(Product $product, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('retrieve', $variant);

        $response = $this->productVariantService->retrieve($product, $variant);

        return response()->json($response);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('create', ProductVariant::class);

        $dto = ProductVariantDTO::fromRequest($request);

        $response = $this->productVariantService->create($product, $dto);

        return response()->json($response, 201);
    }

    public function update(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('update', $variant);

        $dto = ProductVariantUpdateDTO::fromRequest($request);

        $response = $this->productVariantService->update($product, $variant, $dto);

        return response()->json($response);
    }

    public function destroy(Product $product, ProductVariant $variant): JsonResponse
    {
        Gate::authorize('delete', $variant);

        $this->productVariantService->delete($product, $variant);

        return response()->json(null, 204);
    }
}
