<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Master\Http\Controllers\CurrencyController;
use Lahatre\Master\Http\Controllers\LabelController;
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

        Route::get('labels', [LabelController::class, 'index'])->name('labels.index');
        Route::post('labels', [LabelController::class, 'store'])->name('labels.store');
        Route::put('labels/reorder', [LabelController::class, 'reorder'])->name('labels.reorder');
        Route::patch('labels/{label}', [LabelController::class, 'update'])->name('labels.update');
        Route::delete('labels/{label}', [LabelController::class, 'destroy'])->name('labels.destroy');

        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units/upsert', [UnitController::class, 'upsert'])->name('units.upsert');
    });
});
