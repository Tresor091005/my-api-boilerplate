<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
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

            Context::add('auth', array_filter([
                'user_id'         => $user->getAuthIdentifier(),
                'organization_id' => $context->organization()?->getKey(),
                'member_id'       => $context->member()?->id,
                'member_role_id'  => $context->memberRole()?->id,
                'role_id'         => $context->role()?->id,
                'guard'           => $this->resolveCurrentGuard(),
            ]));
        }

        return $next($request);
    }

    /**
     * Resolve the current authentication guard name.
     */
    protected function resolveCurrentGuard(): ?string
    {
        foreach (config('auth.guards') as $guard => $config) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }
}
