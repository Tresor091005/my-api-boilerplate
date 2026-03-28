<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\OptionValueDTO;
use Lahatre\Catalog\DTO\OptionValueFilterDTO;
use Lahatre\Catalog\Http\Resources\OptionValueCollection;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Services\OptionValueService;

class OptionValueController
{
    public function __construct(
        protected OptionValueService $optionValueService
    ) {}

    public function index(Request $request, Option $option): OptionValueCollection
    {
        Gate::authorize('list', OptionValue::class);

        $filters = OptionValueFilterDTO::fromRequest($request);

        return $this->optionValueService->list($option, $filters);
    }

    public function show(Option $option, OptionValue $optionValue): JsonResponse
    {
        Gate::authorize('retrieve', $optionValue);

        $response = $this->optionValueService->retrieve($option, $optionValue);

        return response()->json($response);
    }

    public function store(Request $request, Option $option): JsonResponse
    {
        Gate::authorize('create', OptionValue::class);

        $dto = OptionValueDTO::fromArray([
            ...$request->all(),
            'option_id' => $option->id,
        ]);

        $response = $this->optionValueService->create($option, $dto);

        return response()->json($response, 201);
    }

    public function update(Request $request, Option $option, OptionValue $optionValue): JsonResponse
    {
        Gate::authorize('update', $optionValue);

        $dto = OptionValueDTO::forUpdate($request, $optionValue);

        $response = $this->optionValueService->update($option, $optionValue, $dto);

        return response()->json($response);
    }

    public function destroy(Option $option, OptionValue $optionValue): JsonResponse
    {
        Gate::authorize('delete', $optionValue);

        $this->optionValueService->delete($option, $optionValue);

        return response()->json(null, 204);
    }
}
