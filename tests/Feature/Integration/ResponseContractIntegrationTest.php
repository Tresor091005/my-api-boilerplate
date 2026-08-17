<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Shared\Registries\ResponseContractRegistry;

it('rejects an api route without a response contract before executing it', function (): void {
    $executed = false;

    Route::middleware('api')->get('/testing/missing-response-contract', function () use (&$executed) {
        $executed = true;

        return response()->json(['ok' => true]);
    })->name('testing.missing-response-contract');

    currentTestCase()->disableExceptionHandling();

    try {
        expect(fn () => currentTestCase()->getJson('/testing/missing-response-contract'))
            ->toThrow(InvalidArgumentException::class)
            ->and($executed)->toBeFalse();
    } finally {
        app(ResponseContractRegistry::class)->registerMany([
            'testing.missing-response-contract' => [],
        ]);
    }
});
