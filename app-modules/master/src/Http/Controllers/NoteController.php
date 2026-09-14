<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Master\Data\NoteCreateData;
use Lahatre\Master\Data\NoteFilterData;
use Lahatre\Master\Data\NoteMentionData;
use Lahatre\Master\Data\NoteUpdateData;
use Lahatre\Master\Data\NoteVisibilityUpdateData;
use Lahatre\Master\Http\Requests\NoteCreateRequest;
use Lahatre\Master\Http\Requests\NoteIndexRequest;
use Lahatre\Master\Http\Requests\NoteMentionRequest;
use Lahatre\Master\Http\Requests\NoteUpdateRequest;
use Lahatre\Master\Http\Requests\NoteVisibilityUpdateRequest;
use Lahatre\Master\Http\Resources\NoteCollection;
use Lahatre\Master\Http\Resources\NoteResource;
use Lahatre\Master\Models\Note;
use Lahatre\Master\Services\NoteService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class NoteController
{
    public function __construct(
        private NoteService $noteService,
        private ResponseResponder $responseResponder,
    ) {}

    public function index(NoteIndexRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Note::class);
        $response = $this->noteService->paginate(NoteFilterData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => NoteCollection::make($response)
        );
    }

    public function store(NoteCreateRequest $request): JsonResponse|Response
    {
        $data = NoteCreateData::fromArray($request->validated());
        Gate::authorize('create', [Note::class, $data->visibility]);

        $response = $this->noteService->create($data);

        return $this->responseResponder->respond(
            fn (): JsonResource => NoteResource::make($response),
            status: 201,
        );
    }

    public function show(Note $note): JsonResponse|Response
    {
        Gate::authorize('retrieve', $note);

        $response = $this->noteService->retrieve($note);

        return $this->responseResponder->respond(
            fn (): JsonResource => NoteResource::make($response)
        );
    }

    public function update(NoteUpdateRequest $request, Note $note): JsonResponse|Response
    {
        Gate::authorize('update', $note);

        $response = $this->noteService->update(
            $note,
            NoteUpdateData::fromArray($request->validated(), missingFields: ['body', 'kind', 'expires_at']),
        );

        return $this->responseResponder->respond(
            fn (): JsonResource => NoteResource::make($response)
        );
    }

    public function updateVisibility(NoteVisibilityUpdateRequest $request, Note $note): Response
    {
        $data = NoteVisibilityUpdateData::fromArray($request->validated());
        Gate::authorize('updateVisibility', [$note, $data->visibility]);
        $this->noteService->promoteVisibility($note, $data);

        return response()->noContent();
    }

    public function destroy(Note $note): Response
    {
        Gate::authorize('delete', $note);
        $this->noteService->delete($note);

        return response()->noContent();
    }

    public function pin(Note $note): Response
    {
        Gate::authorize('pin', $note);
        $this->noteService->setPinned($note, true);

        return response()->noContent();
    }

    public function unpin(Note $note): Response
    {
        Gate::authorize('pin', $note);
        $this->noteService->setPinned($note, false);

        return response()->noContent();
    }

    public function addMention(NoteMentionRequest $request, Note $note): Response
    {
        Gate::authorize('mention', $note);
        $this->noteService->addMentions($note, NoteMentionData::fromArray($request->validated()));

        return response()->noContent();
    }

    public function removeMention(NoteMentionRequest $request, Note $note): Response
    {
        Gate::authorize('mention', $note);
        $this->noteService->removeMentions($note, NoteMentionData::fromArray($request->validated()));

        return response()->noContent();
    }

    public function markMentionRead(Note $note): Response
    {
        $this->noteService->markMentionRead($note);

        return response()->noContent();
    }
}
