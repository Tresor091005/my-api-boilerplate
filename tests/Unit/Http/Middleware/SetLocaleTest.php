<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

function localeDuringRequest(?string $language): string
{
    $request = Request::create('/');

    if ($language !== null) {
        $request->headers->set('X-Language', $language);
    }

    $locale = '';
    (new SetLocale)->handle($request, function () use (&$locale): Response {
        $locale = App::getLocale();

        return new Response;
    });

    return $locale;
}

it('answers in the supported language the request asks for, then restores the locale', function (): void {
    expect(localeDuringRequest('fr'))->toBe('fr')
        ->and(App::getLocale())->toBe('en')
        ->and(config('app.locale'))->toBe('en');
});

it('keeps the application locale for a missing or unsupported language', function (): void {
    expect(localeDuringRequest(null))->toBe('en')
        ->and(localeDuringRequest('de'))->toBe('en');
});
