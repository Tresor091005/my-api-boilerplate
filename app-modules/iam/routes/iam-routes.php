<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Iam\Http\Controllers\AuthController;

Route::group([
    'as'         => 'lahatre.iam.',
    'prefix'     => 'v1',
    'middleware' => 'api',
], function (): void {
    // ------- Auth endpoints -------
    Route::group([
        'as'     => 'auth.',
        'prefix' => 'auth',
    ], function (): void {
        Route::post('/{type}/login', [AuthController::class, 'login'])->name('login');
        Route::post('/register', [AuthController::class, 'register'])->name('register');

        Route::group([
            'middleware' => ['auth.api'],
        ], function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('/switch-user-role', [AuthController::class, 'switchUserRole'])->name('switchuserrole');
        });
    });
});
