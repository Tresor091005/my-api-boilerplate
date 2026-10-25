<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Lahatre\Iam\Data\SessionFilterData;
use Lahatre\Iam\Http\Requests\SessionFilterRequest;
use Lahatre\Iam\Http\Resources\SessionResource;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\SessionService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class SessionController
{
    public function __construct(private readonly SessionService $sessions) {}

    public function index(SessionFilterRequest $request): JsonResponse|Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        $sessions = $this->sessions->paginate($user, SessionFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($sessions, SessionResource::class);
    }

    public function destroy(string $session): Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        $this->sessions->delete($user, $session);

        return ApiResponse::noContent();
    }

    public function destroyAll(): Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        $this->sessions->deleteAll($user);

        return ApiResponse::noContent();
    }
}
