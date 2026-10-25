<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lahatre\Iam\Data\GoogleAuthenticationData;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Http\Requests\GoogleAuthenticationRequest;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\GoogleAuthService;
use Symfony\Component\HttpFoundation\Response;

class GoogleAuthController
{
    public function __construct(private readonly GoogleAuthService $google) {}

    public function challenge(Request $request): JsonResponse|Response
    {
        $challenge = $this->google->createChallenge();

        return response()->json($challenge);
    }

    public function verify(GoogleAuthenticationRequest $request): JsonResponse|Response
    {
        $result = $this->google->authenticate(GoogleAuthenticationData::fromArray($request->validated()), SessionData::fromArray([
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]));

        // a first sign-in creates the user, which would make the resource answer 201
        return isset($result['token'])
            ? AuthResource::make($result['user'])->withToken($result['token'])->response()->setStatusCode(200)
            : response()->json($result);
    }

    public function link(GoogleAuthenticationRequest $request): JsonResponse|Response
    {
        $user = authContext()->user();
        assert($user instanceof User);
        Gate::authorize('update', $user);
        $this->google->link($user, GoogleAuthenticationData::fromArray($request->validated()));

        return response()->json(['message' => __('iam::messages.auth.google_linked')], 201);
    }
}
