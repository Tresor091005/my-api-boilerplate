<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Data\LabelCreateData;
use Lahatre\Master\Data\LabelFilterData;
use Lahatre\Master\Data\LabelReorderData;
use Lahatre\Master\Data\LabelUpdateData;
use Lahatre\Master\Http\Requests\LabelCreateRequest;
use Lahatre\Master\Http\Requests\LabelFilterRequest;
use Lahatre\Master\Http\Requests\LabelReorderRequest;
use Lahatre\Master\Http\Requests\LabelUpdateRequest;
use Lahatre\Master\Http\Resources\LabelCollection;
use Lahatre\Master\Http\Resources\LabelResource;
use Lahatre\Master\Models\Label;
use Lahatre\Master\Services\LabelService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final class LabelController
{
    public function __construct(
        protected LabelService $labelService,
        protected ResponseResponder $responseResponder,
    ) {}

    public function index(LabelFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Label::class);

        $filters = LabelFilterData::fromArray($request->validated());
        $response = $this->labelService->paginate($filters);

        return $this->responseResponder->respond(fn (): JsonResource => LabelCollection::make($response));
    }

    public function store(LabelCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Label::class);

        $response = $this->labelService->create(LabelCreateData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => LabelCollection::make($response),
            status: 201,
        );
    }

    public function update(LabelUpdateRequest $request, Label $label): JsonResponse|Response
    {
        Gate::authorize('update', $label);

        $response = $this->labelService->update($label, LabelUpdateData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => LabelResource::make($response));
    }

    public function reorder(LabelReorderRequest $request): Response
    {
        Gate::authorize('reorder', Label::class);
        $this->labelService->reorder(LabelReorderData::fromArray($request->validated()));

        return response()->noContent();
    }

    public function destroy(Label $label): Response
    {
        Gate::authorize('delete', $label);
        $this->labelService->delete($label);

        return response()->noContent();
    }
}
