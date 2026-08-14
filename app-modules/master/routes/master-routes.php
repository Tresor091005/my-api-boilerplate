<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Master\Http\Controllers\CurrencyController;
use Lahatre\Master\Http\Controllers\UnitController;

Route::group([
    'as'         => 'lahatre.master.',
    'prefix'     => 'v1/master',
    'middleware' => 'api',
], function (): void {
    Route::group([
        'middleware' => 'auth.api',
    ], function (): void {
        Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');

        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units/upsert', [UnitController::class, 'upsert'])->name('units.upsert');
    });
});
