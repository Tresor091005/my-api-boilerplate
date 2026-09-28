<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lahatre\Commitment\Exceptions\CommitmentException;
use Lahatre\Commitment\Services\GuestAccessService;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveGuestAccess
{
    public function __construct(private GuestAccessService $access) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $commitment = $this->access->authenticate((string) $request->route('reference'), $request->bearerToken());
        } catch (CommitmentException $exception) {
            abort(401, $exception->getMessage());
        }
        $request->attributes->set('guestCommitment', $commitment);

        return $next($request);
    }
}
