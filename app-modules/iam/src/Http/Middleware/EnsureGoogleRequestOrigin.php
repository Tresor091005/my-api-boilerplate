<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lahatre\Iam\Exceptions\GoogleAuthException;
use Symfony\Component\HttpFoundation\Response;

class EnsureGoogleRequestOrigin
{
    /** Browser requests must originate from the configured frontend and use JSON; native clients may omit Origin. */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        $frontend = parse_url(config('frontend.url'));
        $expected = isset($frontend['scheme'], $frontend['host'])
            ? $frontend['scheme'].'://'.$frontend['host'].(isset($frontend['port']) ? ':'.$frontend['port'] : '')
            : null;
        if (!$request->isJson() || ($origin !== null && ($expected === null || $origin !== $expected))) {
            throw GoogleAuthException::invalidOrigin();
        }

        return $next($request);
    }
}
