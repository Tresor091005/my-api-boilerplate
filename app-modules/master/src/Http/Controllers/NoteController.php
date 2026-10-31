<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Controllers;

use Illuminate\Http\JsonResponse;
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
use Lahatre\Master\Http\Resources\NoteResource;
use Lahatre\Master\Models\Note;
use Lahatre\Master\Services\NoteService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class NoteController
{
    public function __construct(
        private NoteService $noteService,
    ) {}

    public function index(NoteIndexRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Note::class);
        $response = $this->noteService->paginate(NoteFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($response, NoteResource::class);
    }

    public function store(NoteCreateRequest $request): JsonResponse|Response
    {
        $data = NoteCreateData::fromArray($request->validated());
        Gate::authorize('create', [Note::class, $data->visibility]);

        $response = $this->noteService->create($data);

        return ApiResponse::item($response, NoteResource::class, 201);
    }

    public function show(Note $note): JsonResponse|Response
    {
        Gate::authorize('retrieve', $note);

        $response = $this->noteService->retrieve($note);

        return ApiResponse::item($response, NoteResource::class);
    }

    public function update(NoteUpdateRequest $request, Note $note): JsonResponse|Response
    {
        Gate::authorize('update', $note);

        $response = $this->noteService->update(
            $note,
            NoteUpdateData::fromArray($request->validated(), missingFields: ['body', 'kind', 'expires_at']),
        );

        return ApiResponse::item($response, NoteResource::class);
    }

    public function updateVisibility(NoteVisibilityUpdateRequest $request, Note $note): \Illuminate\Http\Response
    {
        $data = NoteVisibilityUpdateData::fromArray($request->validated());
        Gate::authorize('updateVisibility', [$note, $data->visibility]);
        $this->noteService->promoteVisibility($note, $data);

        return ApiResponse::noContent();
    }

    public function destroy(Note $note): \Illuminate\Http\Response
    {
        Gate::authorize('delete', $note);
        $this->noteService->delete($note);

        return ApiResponse::noContent();
    }

    public function pin(Note $note): \Illuminate\Http\Response
    {
        Gate::authorize('pin', $note);
        $this->noteService->setPinned($note, true);

        return ApiResponse::noContent();
    }

    public function unpin(Note $note): \Illuminate\Http\Response
    {
        Gate::authorize('pin', $note);
        $this->noteService->setPinned($note, false);

        return ApiResponse::noContent();
    }

    public function addMention(NoteMentionRequest $request, Note $note): \Illuminate\Http\Response
    {
        Gate::authorize('mention', $note);
        $this->noteService->addMentions($note, NoteMentionData::fromArray($request->validated()));

        return ApiResponse::noContent();
    }

    public function removeMention(NoteMentionRequest $request, Note $note): \Illuminate\Http\Response
    {
        Gate::authorize('mention', $note);
        $this->noteService->removeMentions($note, NoteMentionData::fromArray($request->validated()));

        return ApiResponse::noContent();
    }

    public function markMentionRead(Note $note): \Illuminate\Http\Response
    {
        $this->noteService->markMentionRead($note);

        return ApiResponse::noContent();
    }
}
