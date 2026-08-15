<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\OptionData;
use Lahatre\Catalog\Data\OptionFilterData;
use Lahatre\Catalog\Http\Requests\OptionFilterRequest;
use Lahatre\Catalog\Http\Requests\OptionRequest;
use Lahatre\Catalog\Http\Resources\OptionCollection;
use Lahatre\Catalog\Http\Resources\OptionResource;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Services\OptionService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class OptionController
{
    public function __construct(
        protected OptionService $optionService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(OptionFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Option::class);

        $filters = OptionFilterData::fromArray($request->validated());

        $response = $this->optionService->paginate($filters);

        return $this->responseResponder->respond(fn (): JsonResource => OptionCollection::make($response));
    }

    public function show(Option $option): JsonResponse|Response
    {
        Gate::authorize('retrieve', $option);

        $response = $this->optionService->retrieve($option);

        return $this->responseResponder->respond(fn (): JsonResource => OptionResource::make($response));
    }

    public function store(OptionRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Option::class);

        $data = OptionData::fromArray($request->validated());

        $response = $this->optionService->create($data);

        return $this->responseResponder->respond(
            fn (): JsonResource => OptionResource::make($response),
            status: 201,
        );
    }

    public function update(OptionRequest $request, Option $option): JsonResponse|Response
    {
        Gate::authorize('update', $option);

        $data = OptionData::fromArray(
            $request->validated(),
            missingFields: ['name', 'values'],
        );

        $response = $this->optionService->update($option, $data);

        return $this->responseResponder->respond(fn (): JsonResource => OptionResource::make($response));
    }

    public function destroy(Option $option): Response
    {
        Gate::authorize('delete', $option);

        $this->optionService->delete($option);

        return response()->noContent();
    }
}
