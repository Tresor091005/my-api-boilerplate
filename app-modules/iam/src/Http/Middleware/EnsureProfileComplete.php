<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lahatre\Iam\Models\User;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && !$user->hasCompleteProfile()) {
            return response()->json([
                'message' => __('iam::exceptions.auth.profile_incomplete'),
                'code'    => 'profile_incomplete',
            ], 403);
        }

        return $next($request);
    }
}
