<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\OptionDTO;
use Lahatre\Catalog\DTO\OptionFilterDTO;
use Lahatre\Catalog\Http\Resources\OptionCollection;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Services\OptionService;

class OptionController
{
    public function __construct(
        protected OptionService $optionService
    ) {}

    public function index(Request $request): OptionCollection
    {
        Gate::authorize('list', Option::class);

        $filters = OptionFilterDTO::fromRequest($request);

        return $this->optionService->list($filters);
    }

    public function show(Option $option): JsonResponse
    {
        Gate::authorize('retrieve', $option);

        $response = $this->optionService->retrieve($option);

        return response()->json($response);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Option::class);

        $dto = OptionDTO::fromRequest($request);

        $response = $this->optionService->create($dto);

        return response()->json($response, 201);
    }

    public function update(Request $request, Option $option): JsonResponse
    {
        Gate::authorize('update', $option);

        $dto = OptionDTO::forUpdate($request, $option);

        $response = $this->optionService->update($option, $dto);

        return response()->json($response);
    }

    public function destroy(Option $option): JsonResponse
    {
        Gate::authorize('delete', $option);

        $this->optionService->delete($option);

        return response()->json(null, 204);
    }
}
