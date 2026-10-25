<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Lahatre\Iam\Data\LoginData;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Http\Requests\EmailChallengeRequest;
use Lahatre\Iam\Http\Requests\EmailChallengeVerificationRequest;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Services\EmailLoginService;
use Symfony\Component\HttpFoundation\Response;

class EmailLoginController
{
    public function __construct(private readonly EmailLoginService $login) {}

    public function store(EmailChallengeRequest $request): JsonResponse|Response
    {
        $id = $this->login->requestCode($request->validated('email'));

        return response()->json(['message' => __('iam::messages.auth.login_code_sent'), 'challenge_id' => $id]);
    }

    public function verify(EmailChallengeVerificationRequest $request): JsonResponse|Response
    {
        $result = $this->login->verify(LoginData::fromArray($request->validated()), SessionData::fromArray([
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]));

        // a first sign-in creates the user, which would make the resource answer 201
        return AuthResource::make($result['user'])->withToken($result['token'])->response()->setStatusCode(200);
    }
}
