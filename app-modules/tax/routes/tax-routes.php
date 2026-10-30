<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Tax\Http\Controllers\TaxController;
use Lahatre\Tax\Http\Controllers\TaxProfileCalculationController;
use Lahatre\Tax\Http\Controllers\TaxProfileController;

Route::group([
    'as'         => 'lahatre.tax.',
    'prefix'     => 'v1/tax',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::apiResource('taxes', TaxController::class);
        Route::apiResource('profiles', TaxProfileController::class);
        Route::post('profiles/{profile}/calculations', [TaxProfileCalculationController::class, 'store'])
            ->name('profiles.calculations.store');
    });
});
