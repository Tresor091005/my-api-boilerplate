<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Commitment\Data\EvidenceData;
use Lahatre\Commitment\Http\Requests\EvidenceCreateRequest;
use Lahatre\Commitment\Http\Resources\ServiceEvidenceCollection;
use Lahatre\Commitment\Http\Resources\ServiceEvidenceResource;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceDeliverable;
use Lahatre\Commitment\Models\ServiceEvidence;
use Lahatre\Commitment\Services\CommitmentService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServiceEvidenceController
{
    public function __construct(private CommitmentService $service, private ResponseResponder $responder) {}

    public function index(ServiceCommitment $commitment, string $unit): JsonResponse|Response
    {
        Gate::authorize('retrieve', $commitment);
        $deliverable = ServiceDeliverable::query()->where('organization_id', currentOrganizationId())
            ->where('commitment_id', $commitment->id)->whereKey($unit)->firstOrFail();
        $evidence = ServiceEvidence::query()->where('organization_id', currentOrganizationId())
            ->where('commitment_id', $commitment->id)->where('deliverable_id', $deliverable->id)->orderBy('version')->orderBy('id')->cursorPaginate(50);

        return $this->responder->respond(fn () => ServiceEvidenceCollection::make($evidence));
    }

    public function store(EvidenceCreateRequest $request, ServiceCommitment $commitment, string $unit): JsonResponse|Response
    {
        Gate::authorize('update', $commitment);
        $model = ServiceDeliverable::query()->where('organization_id', currentOrganizationId())
            ->where('commitment_id', $commitment->id)->whereKey($unit)->firstOrFail();
        $evidence = $this->service->submitEvidence($commitment, $model, EvidenceData::fromArray($request->validated()), auth()->id());

        return $this->responder->respond(fn () => ServiceEvidenceResource::make($evidence), 201);
    }
}
