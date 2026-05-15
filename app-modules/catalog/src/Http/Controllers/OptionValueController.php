<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\DTO\OptionValueDTO;
use Lahatre\Catalog\DTO\OptionValueFilterDTO;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Services\OptionValueService;

class OptionValueController
{
    public function __construct(
        protected OptionValueService $optionValueService
    ) {}

    public function index(Request $request, Option $option): AnonymousResourceCollection
    {
        Gate::authorize('retrieve', $option);
        Gate::authorize('list', OptionValue::class);

        $filters = OptionValueFilterDTO::fromRequest($request);

        return $this->optionValueService->list($option, $filters);
    }

    public function show(Option $option, OptionValue $value): JsonResponse
    {
        Gate::authorize('retrieve', $option);
        Gate::authorize('retrieve', $value);

        $response = $this->optionValueService->retrieve($option, $value);

        return response()->json($response);
    }

    public function store(Request $request, Option $option): JsonResponse
    {
        Gate::authorize('update', $option);
        Gate::authorize('create', OptionValue::class);

        $dto = OptionValueDTO::fromArray([
            ...$request->all(),
            'option_id' => $option->id,
        ]);

        $response = $this->optionValueService->create($option, $dto);

        return response()->json($response, 201);
    }

    public function update(Request $request, Option $option, OptionValue $value): JsonResponse
    {
        Gate::authorize('update', $option);
        Gate::authorize('update', $value);

        $dto = OptionValueDTO::forUpdate($request, $value);

        $response = $this->optionValueService->update($option, $value, $dto);

        return response()->json($response);
    }

    public function destroy(Option $option, OptionValue $value): JsonResponse
    {
        Gate::authorize('update', $option);
        Gate::authorize('delete', $value);

        $this->optionValueService->delete($option, $value);

        return response()->json(null, 204);
    }
}
