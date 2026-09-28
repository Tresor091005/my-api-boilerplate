<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Commitment\Data\CommitmentData;
use Lahatre\Commitment\Http\Requests\CommitmentCreateRequest;
use Lahatre\Commitment\Http\Requests\CommitmentUpdateRequest;
use Lahatre\Commitment\Http\Requests\ProposalCreateRequest;
use Lahatre\Commitment\Http\Resources\ServiceCommitmentCollection;
use Lahatre\Commitment\Http\Resources\ServiceCommitmentProposalResource;
use Lahatre\Commitment\Http\Resources\ServiceCommitmentResource;
use Lahatre\Commitment\Models\ServiceAuditEvent;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceCommitmentProposal;
use Lahatre\Commitment\Services\CommitmentService;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServiceCommitmentController
{
    public function __construct(private CommitmentService $service, private ResponseResponder $responder) {}

    public function index(): JsonResponse|Response
    {
        Gate::authorize('list', ServiceCommitment::class);

        return $this->responder->respond(fn () => ServiceCommitmentCollection::make($this->service->list()));
    }

    public function store(CommitmentCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', ServiceCommitment::class);
        $commitment = $this->service->create(CommitmentData::fromArray($request->validated()), auth()->id());

        return $this->responder->respond(fn () => ServiceCommitmentResource::make($commitment), 201);
    }

    public function show(ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('retrieve', $commitment);

        return $this->responder->respond(fn () => ServiceCommitmentResource::make($this->service->retrieve($commitment->id)));
    }

    public function update(CommitmentUpdateRequest $request, ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('update', $commitment);
        $validated = $request->validated();
        $proposal = $this->service->updateDraft(
            $commitment,
            $validated['title'],
            array_key_exists('terms', $validated) ? $validated['terms'] : MissingValue::Instance,
            auth()->id(),
        );

        return $this->responder->respond(fn () => ServiceCommitmentProposalResource::make($proposal));
    }

    public function proposals(ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('retrieve', $commitment);
        $proposals = ServiceCommitmentProposal::query()->where('organization_id', currentOrganizationId())
            ->where('commitment_id', $commitment->id)->orderBy('version')->orderBy('id')->cursorPaginate(50);

        return $this->responder->respond(fn () => ServiceCommitmentProposalResource::collection($proposals));
    }

    public function submitProposal(ProposalCreateRequest $request, ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('update', $commitment);
        $validated = $request->validated();
        $proposal = $this->service->submitProposal(
            $commitment,
            $validated['title'] ?? null,
            array_key_exists('terms', $validated) ? $validated['terms'] : MissingValue::Instance,
            auth()->id(),
        );

        return $this->responder->respond(fn () => ServiceCommitmentProposalResource::make($proposal), 201);
    }

    public function requestClosure(ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('update', $commitment);
        $event = $this->service->requestClosure($commitment, auth()->id());

        return $this->responder->respond(fn () => ['request_id' => $event->id], 201);
    }

    public function requestReopening(ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('update', $commitment);
        $event = $this->service->requestReopening($commitment, auth()->id());

        return $this->responder->respond(fn () => ['request_id' => $event->id], 201);
    }

    public function report(ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('retrieve', $commitment);

        return $this->responder->respond(fn () => $this->service->report($commitment));
    }

    public function events(ServiceCommitment $commitment): JsonResponse|Response
    {
        Gate::authorize('retrieve', $commitment);
        $events = ServiceAuditEvent::query()->where('organization_id', currentOrganizationId())
            ->where('commitment_id', $commitment->id)->orderBy('created_at')->orderBy('id')->cursorPaginate(50);

        return $this->responder->respond(fn () => $events->toArray());
    }
}
