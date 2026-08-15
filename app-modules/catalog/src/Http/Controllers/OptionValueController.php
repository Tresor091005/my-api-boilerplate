<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
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
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class OptionValueController
{
    public function __construct(
        protected OptionValueService $optionValueService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(OptionValueFilterRequest $request, Option $option): JsonResponse|Response
    {
        Gate::authorize('retrieve', $option);
        Gate::authorize('list', OptionValue::class);

        $filters = OptionValueFilterData::fromArray($request->validated());

        $response = $this->optionValueService->list($option, $filters);

        return $this->responseResponder->respond(fn (): JsonResource => OptionValueResource::collection($response));
    }

    public function show(Option $option, OptionValue $value): JsonResponse|Response
    {
        Gate::authorize('retrieve', $option);
        Gate::authorize('retrieve', $value);

        $response = $this->optionValueService->retrieve($option, $value);

        return $this->responseResponder->respond(fn (): JsonResource => OptionValueResource::make($response));
    }

    public function store(StoreOptionValueRequest $request, Option $option): JsonResponse|Response
    {
        Gate::authorize('update', $option);
        Gate::authorize('create', OptionValue::class);

        $data = OptionValueData::fromArray([
            ...$request->validated(),
            'option_id' => $option->id,
        ]);

        $response = $this->optionValueService->create($option, $data);

        return $this->responseResponder->respond(
            fn (): JsonResource => OptionValueResource::collection($response),
            status: 201,
        );
    }

    public function update(UpdateOptionValueRequest $request, Option $option, OptionValue $value): JsonResponse|Response
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

        return $this->responseResponder->respond(fn (): JsonResource => OptionValueResource::make($response));
    }

    public function destroy(Option $option, OptionValue $value): Response
    {
        Gate::authorize('update', $option);
        Gate::authorize('delete', $value);

        $this->optionValueService->delete($option, $value);

        return response()->noContent();
    }
}
