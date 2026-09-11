<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Library\Http\Controllers\FileController;
use Lahatre\Library\Http\Controllers\FolderController;

Route::group([
    'as'         => 'lahatre.library.',
    'prefix'     => 'v1/library',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::get('files/{file}/content', [FileController::class, 'content'])->name('files.content');
        Route::get('trash/files', [FileController::class, 'trash'])->name('trash.files');
        Route::post('files/{file}/restore', [FileController::class, 'restore'])
            ->withTrashed()
            ->name('files.restore');
        Route::apiResource('files', FileController::class);
        Route::apiResource('folders', FolderController::class);
    });
});
