<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTeamPermissionsId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = authContext();

        // TODO: correct this using authContext()
        setPermissionsTeamId(getDefaultTeamId());

        auth()->user()->unsetRelation('roles')->unsetRelation('permissions');

        return $next($request);
    }
}
