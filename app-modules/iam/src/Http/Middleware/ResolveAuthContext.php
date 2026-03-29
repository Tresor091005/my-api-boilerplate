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
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = authContext();

        $context->clear();

        $user = auth()->user();

        if ($user) {
            $token = $user->currentAccessToken();

            $metadata = ($token instanceof PersonalAccessToken) ? $token->metadata : null;

            $context->setContext($user, $metadata);
        }

        return $next($request);
    }
}
