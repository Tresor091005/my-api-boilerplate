<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTeamPermissionsId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!authContext()->organization() || !authContext()->memberRole()) {
            throw new AuthenticationException(__('iam::exceptions.auth.invalid_session_context'));
        }

        setPermissionsTeamId(authContext()->organization()->getKey());

        authContext()->user()->unsetRelation('roles')->unsetRelation('permissions');

        authContext()->memberRole()->unsetRelation('roles')->unsetRelation('permissions');

        return $next($request);
    }
}
