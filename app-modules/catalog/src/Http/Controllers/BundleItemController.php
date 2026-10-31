<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\BundleItemData;
use Lahatre\Catalog\Data\BundleItemQuantityData;
use Lahatre\Catalog\Http\Requests\BundleItemCreateRequest;
use Lahatre\Catalog\Http\Requests\BundleItemDeleteRequest;
use Lahatre\Catalog\Http\Requests\BundleItemUpdateRequest;
use Lahatre\Catalog\Http\Resources\BundleItemResource;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleItem;
use Lahatre\Catalog\Services\BundleService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class BundleItemController
{
    public function __construct(
        private BundleService $bundleService,
    ) {}

    public function store(BundleItemCreateRequest $request, Bundle $bundle): JsonResponse|Response
    {
        Gate::authorize('manageComposition', $bundle);
        $itemsData = collect($request->validated('items'))->map(BundleItemData::fromArray(...));
        $items = $this->bundleService->addItems($bundle, $itemsData);

        return ApiResponse::list($items, BundleItemResource::class, 201);
    }

    public function update(
        BundleItemUpdateRequest $request,
        Bundle $bundle,
        BundleItem $item,
    ): JsonResponse|Response {
        Gate::authorize('manageComposition', $bundle);
        $item = $this->bundleService->updateItem(
            $bundle,
            $item,
            BundleItemQuantityData::fromArray($request->validated()),
        );

        return ApiResponse::item($item, BundleItemResource::class);
    }

    public function destroy(BundleItemDeleteRequest $request, Bundle $bundle): \Illuminate\Http\Response
    {
        Gate::authorize('manageComposition', $bundle);
        $this->bundleService->removeItems($bundle, $request->validated('ids'));

        return ApiResponse::noContent();
    }
}
