<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Localizable;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    use Localizable;

    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->requestedLocale($request);

        if ($locale === null) {
            return $next($request);
        }

        return $this->withLocale($locale, fn (): Response => $next($request));
    }

    private function requestedLocale(Request $request): ?string
    {
        $language = $request->header('X-Language');

        return is_string($language) && in_array($language, config()->array('app.supported_locales'), true)
            ? $language
            : null;
    }
}
