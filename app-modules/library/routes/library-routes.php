<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Library\Http\Controllers\FolderController;

Route::group([
    'as'         => 'lahatre.library.',
    'prefix'     => 'v1/library',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::apiResource('folders', FolderController::class);
    });
});
