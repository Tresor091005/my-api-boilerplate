<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Lahatre\Commitment\Http\Requests\GuestChallengeRequest;
use Lahatre\Commitment\Http\Requests\GuestSessionRequest;
use Lahatre\Commitment\Services\GuestAccessService;
use Symfony\Component\HttpFoundation\Response;

final readonly class GuestAccessController
{
    public function __construct(private GuestAccessService $access) {}

    public function challenge(GuestChallengeRequest $request): Response
    {
        $this->access->sendCode($request->validated('reference'));

        return response()->noContent();
    }

    public function session(GuestSessionRequest $request): JsonResponse
    {
        $token = $this->access->verifyCode($request->validated('reference'), (string) $request->validated('code'));

        return response()->json(['token' => $token, 'expires_in' => 3600], 201);
    }
}
