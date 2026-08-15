<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\OptionValueData;
use Lahatre\Catalog\Data\OptionValueFilterData;
use Lahatre\Catalog\Http\Requests\OptionValueFilterRequest;
use Lahatre\Catalog\Http\Requests\StoreOptionValueRequest;
use Lahatre\Catalog\Http\Requests\UpdateOptionValueRequest;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Services\OptionValueService;

class OptionValueController
{
    public function __construct(
        protected OptionValueService $optionValueService
    ) {}

    public function index(OptionValueFilterRequest $request, Option $option): AnonymousResourceCollection
    {
        Gate::authorize('retrieve', $option);
        Gate::authorize('list', OptionValue::class);

        $filters = OptionValueFilterData::fromArray($request->validated());

        return $this->optionValueService->list($option, $filters);
    }

    public function show(Option $option, OptionValue $value): JsonResponse
    {
        Gate::authorize('retrieve', $option);
        Gate::authorize('retrieve', $value);

        $response = $this->optionValueService->retrieve($option, $value);

        return response()->json($response);
    }

    public function store(StoreOptionValueRequest $request, Option $option): JsonResponse
    {
        Gate::authorize('update', $option);
        Gate::authorize('create', OptionValue::class);

        $data = OptionValueData::fromArray([
            ...$request->validated(),
            'option_id' => $option->id,
        ]);

        $response = $this->optionValueService->create($option, $data);

        return response()->json($response, 201);
    }

    public function update(UpdateOptionValueRequest $request, Option $option, OptionValue $value): JsonResponse
    {
        Gate::authorize('update', $option);
        Gate::authorize('update', $value);

        $data = OptionValueData::fromArray(
            [
                ...$request->validated(),
                'option_id' => $option->id,
            ],
            missingFields: ['value'],
        );

        $response = $this->optionValueService->update($option, $value, $data);

        return response()->json($response);
    }

    public function destroy(Option $option, OptionValue $value): Response
    {
        Gate::authorize('update', $option);
        Gate::authorize('delete', $value);

        $this->optionValueService->delete($option, $value);

        return response()->noContent();
    }
}
