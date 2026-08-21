<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Master\Http\Controllers\CurrencyController;
use Lahatre\Master\Http\Controllers\LabelController;
use Lahatre\Master\Http\Controllers\NoteController;
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

        Route::put('labels/reorder', [LabelController::class, 'reorder'])->name('labels.reorder');
        Route::apiResource('labels', LabelController::class)->except(['show']);

        Route::apiResource('notes', NoteController::class);
        Route::patch('notes/{note}/visibility', [NoteController::class, 'updateVisibility'])->name('notes.visibility.update');
        Route::post('notes/{note}/pin', [NoteController::class, 'pin'])->name('notes.pin');
        Route::delete('notes/{note}/pin', [NoteController::class, 'unpin'])->name('notes.unpin');
        Route::post('notes/{note}/mentions', [NoteController::class, 'addMention'])->name('notes.mentions.store');
        Route::delete('notes/{note}/mentions', [NoteController::class, 'removeMention'])->name('notes.mentions.destroy');
        Route::post('notes/{note}/mentions/read', [NoteController::class, 'markMentionRead'])->name('notes.mentions.read');

        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units/upsert', [UnitController::class, 'upsert'])->name('units.upsert');
    });
});
