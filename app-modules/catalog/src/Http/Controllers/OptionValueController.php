<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\OptionValueData;
use Lahatre\Catalog\Data\OptionValueFilterData;
use Lahatre\Catalog\Http\Requests\OptionValueFilterRequest;
use Lahatre\Catalog\Http\Requests\StoreOptionValueRequest;
use Lahatre\Catalog\Http\Requests\UpdateOptionValueRequest;
use Lahatre\Catalog\Http\Resources\OptionValueResource;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Services\OptionValueService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class OptionValueController
{
    public function __construct(
        protected OptionValueService $optionValueService,
    ) {}

    public function index(OptionValueFilterRequest $request, Option $option): JsonResponse|Response
    {
        Gate::authorize('retrieve', $option);

        $filters = OptionValueFilterData::fromArray($request->validated());

        $response = $this->optionValueService->list($option, $filters);

        return ApiResponse::list($response, OptionValueResource::class);
    }

    public function show(Option $option, OptionValue $value): JsonResponse|Response
    {
        Gate::authorize('retrieve', $option);

        $response = $this->optionValueService->retrieve($option, $value);

        return ApiResponse::item($response, OptionValueResource::class);
    }

    public function store(StoreOptionValueRequest $request, Option $option): JsonResponse|Response
    {
        Gate::authorize('update', $option);

        $data = OptionValueData::fromArray([
            ...$request->validated(),
            'option_id' => $option->id,
        ]);

        $response = $this->optionValueService->create($option, $data);

        return ApiResponse::list($response, OptionValueResource::class, 201);
    }

    public function update(UpdateOptionValueRequest $request, Option $option, OptionValue $value): JsonResponse|Response
    {
        Gate::authorize('update', $option);

        $data = OptionValueData::fromArray(
            [
                ...$request->validated(),
                'option_id' => $option->id,
            ],
            missingFields: ['value'],
        );

        $response = $this->optionValueService->update($option, $value, $data);

        return ApiResponse::item($response, OptionValueResource::class);
    }

    public function destroy(Option $option, OptionValue $value): Response
    {
        Gate::authorize('update', $option);

        $this->optionValueService->delete($option, $value);

        return ApiResponse::noContent();
    }
}
