<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Commitment\Data\DeliverableData;
use Lahatre\Commitment\Http\Requests\DeliverableRequest;
use Lahatre\Commitment\Http\Resources\ServiceDeliverableCollection;
use Lahatre\Commitment\Http\Resources\ServiceDeliverableResource;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceDeliverable;
use Lahatre\Commitment\Services\CommitmentService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServiceDeliverableController
{
    public function __construct(private CommitmentService $service, private ResponseResponder $responder) {}

    public function index(ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('retrieve', $commitment);
        $units = ServiceDeliverable::query()->where('organization_id', currentOrganizationId())
            ->where('commitment_id', $commitment->id)->orderBy('created_at')->orderBy('id')->cursorPaginate(50);

        return $this->responder->respond(fn () => ServiceDeliverableCollection::make($units));
    }

    public function store(DeliverableRequest $request, ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('update', $commitment);
        $unit = $this->service->addDeliverable($commitment, DeliverableData::fromArray($request->validated()), auth()->id());

        return $this->responder->respond(fn () => ServiceDeliverableResource::make($unit), 201);
    }

    public function show(ServiceCommitment $commitment, string $unit): JsonResponse|Response
    {
        Gate::authorize('retrieve', $commitment);
        $model = ServiceDeliverable::query()->where('organization_id', currentOrganizationId())
            ->where('commitment_id', $commitment->id)->whereKey($unit)->firstOrFail();

        return $this->responder->respond(fn () => ServiceDeliverableResource::make($model));
    }

    public function update(DeliverableRequest $request, ServiceCommitment $commitment, string $unit): JsonResponse|Response
    {
        Gate::authorize('update', $commitment);
        $model = ServiceDeliverable::query()->where('organization_id', currentOrganizationId())
            ->where('commitment_id', $commitment->id)->whereKey($unit)->firstOrFail();
        $updated = $this->service->updateDeliverable($commitment, $model, DeliverableData::fromArray(
            $request->validated(),
            missingFields: ['title', 'description', 'scheduled_at', 'quantity', 'display_unit_code'],
        ), auth()->id());

        return $this->responder->respond(fn () => ServiceDeliverableResource::make($updated));
    }
}
