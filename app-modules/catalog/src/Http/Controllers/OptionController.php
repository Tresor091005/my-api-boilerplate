<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\OptionData;
use Lahatre\Catalog\Data\OptionFilterData;
use Lahatre\Catalog\Http\Requests\OptionFilterRequest;
use Lahatre\Catalog\Http\Requests\OptionRequest;
use Lahatre\Catalog\Http\Resources\OptionCollection;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Services\OptionService;

class OptionController
{
    public function __construct(
        protected OptionService $optionService
    ) {}

    public function index(OptionFilterRequest $request): OptionCollection
    {
        Gate::authorize('list', Option::class);

        $filters = OptionFilterData::fromArray($request->validated());

        return $this->optionService->list($filters);
    }

    public function show(Option $option): JsonResponse
    {
        Gate::authorize('retrieve', $option);

        $response = $this->optionService->retrieve($option);

        return response()->json($response);
    }

    public function store(OptionRequest $request): JsonResponse
    {
        Gate::authorize('create', Option::class);

        $data = OptionData::fromArray($request->validated());

        $response = $this->optionService->create($data);

        return response()->json($response, 201);
    }

    public function update(OptionRequest $request, Option $option): JsonResponse
    {
        Gate::authorize('update', $option);

        $data = OptionData::fromArray(
            $request->validated(),
            missingFields: ['name', 'values'],
        );

        $response = $this->optionService->update($option, $data);

        return response()->json($response);
    }

    public function destroy(Option $option): Response
    {
        Gate::authorize('delete', $option);

        $this->optionService->delete($option);

        return response()->noContent();
    }
}
