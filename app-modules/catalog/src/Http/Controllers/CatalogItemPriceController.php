<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\CatalogItemPriceAmountData;
use Lahatre\Catalog\Data\CatalogItemPriceData;
use Lahatre\Catalog\Http\Requests\CatalogItemPriceCreateRequest;
use Lahatre\Catalog\Http\Requests\CatalogItemPriceDeleteRequest;
use Lahatre\Catalog\Http\Requests\CatalogItemPriceUpdateRequest;
use Lahatre\Catalog\Http\Resources\CatalogItemPriceResource;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Services\CatalogItemPriceService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The prices of a variant, bundle or service, addressed by its catalog item id
 * (the same id as the variant, bundle or service).
 */
final readonly class CatalogItemPriceController
{
    public function __construct(
        private CatalogItemPriceService $priceService,
    ) {}

    public function index(CatalogItem $item): JsonResponse|Response
    {
        Gate::authorize('list', [CatalogItemPrice::class, $item]);

        return ApiResponse::list($this->priceService->list($item), CatalogItemPriceResource::class);
    }

    public function store(CatalogItemPriceCreateRequest $request, CatalogItem $item): JsonResponse|Response
    {
        Gate::authorize('create', [CatalogItemPrice::class, $item]);
        $prices = $this->priceService->create(
            $item,
            collect($request->validated('prices'))->map(CatalogItemPriceData::fromArray(...)),
        );

        return ApiResponse::list($prices, CatalogItemPriceResource::class, 201);
    }

    public function update(CatalogItemPriceUpdateRequest $request, CatalogItem $item, CatalogItemPrice $price): JsonResponse|Response
    {
        Gate::authorize('update', $price);
        $price = $this->priceService->update($item, $price, CatalogItemPriceAmountData::fromArray($request->validated()));

        return ApiResponse::item($price, CatalogItemPriceResource::class);
    }

    public function destroy(CatalogItemPriceDeleteRequest $request, CatalogItem $item): \Illuminate\Http\Response
    {
        Gate::authorize('delete', [CatalogItemPrice::class, $item]);
        $this->priceService->delete($item, $request->validated('ids'));

        return ApiResponse::noContent();
    }
}
