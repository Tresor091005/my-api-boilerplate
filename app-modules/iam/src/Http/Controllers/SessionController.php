<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Data\SessionFilterData;
use Lahatre\Iam\Http\Requests\SessionFilterRequest;
use Lahatre\Iam\Http\Resources\SessionCollection;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\SessionService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class SessionController
{
    public function __construct(private readonly SessionService $sessions, private readonly ResponseResponder $responder) {}

    public function index(SessionFilterRequest $request): JsonResponse|Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        $sessions = $this->sessions->paginate($user, SessionFilterData::fromArray($request->validated()));

        return $this->responder->respond(fn (): JsonResource => SessionCollection::make($sessions));
    }

    public function destroy(int $session): Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        $this->sessions->delete($user, $session);

        return response()->noContent();
    }

    public function destroyAll(): Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        $this->sessions->deleteAll($user);

        return response()->noContent();
    }
}
