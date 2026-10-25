<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\OptionData;
use Lahatre\Catalog\Data\OptionFilterData;
use Lahatre\Catalog\Http\Requests\OptionFilterRequest;
use Lahatre\Catalog\Http\Requests\OptionRequest;
use Lahatre\Catalog\Http\Resources\OptionResource;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Services\OptionService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class OptionController
{
    public function __construct(
        protected OptionService $optionService,
    ) {}

    public function index(OptionFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Option::class);

        $filters = OptionFilterData::fromArray($request->validated());

        $response = $this->optionService->paginate($filters);

        return ApiResponse::cursorPage($response, OptionResource::class);
    }

    public function show(Option $option): JsonResponse|Response
    {
        Gate::authorize('retrieve', $option);

        $response = $this->optionService->retrieve($option);

        return ApiResponse::item($response, OptionResource::class);
    }

    public function store(OptionRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Option::class);

        $data = OptionData::fromArray($request->validated());

        $response = $this->optionService->create($data);

        return ApiResponse::item($response, OptionResource::class, 201);
    }

    public function update(OptionRequest $request, Option $option): JsonResponse|Response
    {
        Gate::authorize('update', $option);

        $data = OptionData::fromArray(
            $request->validated(),
            missingFields: ['name', 'values'],
        );

        $response = $this->optionService->update($option, $data);

        return ApiResponse::item($response, OptionResource::class);
    }

    public function destroy(Option $option): Response
    {
        Gate::authorize('delete', $option);

        $this->optionService->delete($option);

        return ApiResponse::noContent();
    }
}
