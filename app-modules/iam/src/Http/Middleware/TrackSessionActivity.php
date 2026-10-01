<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Services\SessionService;
use Symfony\Component\HttpFoundation\Response;

class TrackSessionActivity
{
    public function __construct(private readonly SessionService $sessions) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $this->sessions->recordActivity($token, SessionData::fromArray([
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
            ]));
        }

        return $next($request);
    }
}
