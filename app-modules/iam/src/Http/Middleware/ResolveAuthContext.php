<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ResolveAuthContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = authContext();

        $user = auth()->user();

        if ($user) {
            $context->setUser($user);

            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                // TODO setting anything that can need to be set using personal_access_tokens_metadata
                // $context->setToken($token);
            }
        }

        return $next($request);
    }
}
