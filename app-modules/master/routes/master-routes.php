<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Master\Http\Controllers\CurrencyController;
use Lahatre\Master\Http\Controllers\TagController;
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

        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
        Route::post('tags', [TagController::class, 'store'])->name('tags.store');
        Route::get('taggables/{taggable_type}/{taggable_id}/tags', [TagController::class, 'taggableTags'])->name('taggables.tags.index');
        Route::put('tags/reorder', [TagController::class, 'reorder'])->name('tags.reorder');
        Route::patch('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
        Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units/upsert', [UnitController::class, 'upsert'])->name('units.upsert');
    });
});
