<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\PlatformAccessService;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAccess
{
    public function __construct(private readonly PlatformAccessService $platformAccess) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = authContext();
        $user = $context->user();

        if (!$user instanceof User || !$context->hasPlatformToken() || !$this->platformAccess->isPlatformUser($user)) {
            throw new AuthorizationException(__('iam::exceptions.platform.access_required'));
        }

        $context->confirmPlatformAccess();

        return $next($request);
    }
}
