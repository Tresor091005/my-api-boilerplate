<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Data\LoginData;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Http\Requests\EmailChallengeRequest;
use Lahatre\Iam\Http\Requests\EmailChallengeVerificationRequest;
use Lahatre\Iam\Http\Resources\AuthResource;
use Lahatre\Iam\Services\EmailLoginService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

class EmailLoginController
{
    public function __construct(private readonly EmailLoginService $login, private readonly ResponseResponder $responder) {}

    public function store(EmailChallengeRequest $request): JsonResponse|Response
    {
        $id = $this->login->requestCode($request->validated('email'));

        return $this->responder->respond(fn (): array => ['message' => __('iam::messages.auth.login_code_sent'), 'challenge_id' => $id]);
    }

    public function verify(EmailChallengeVerificationRequest $request): JsonResponse|Response
    {
        $result = $this->login->verify(LoginData::fromArray($request->validated()), SessionData::fromArray([
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]));

        return $this->responder->respond(fn (): JsonResource => AuthResource::make($result['user'])->withToken($result['token']));
    }
}
