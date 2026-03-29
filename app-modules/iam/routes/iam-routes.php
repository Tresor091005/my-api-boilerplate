<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Iam\Http\Controllers\AuthController;
use Lahatre\Iam\Http\Middleware\ResolveAuthContext;

Route::group([
    'as'         => 'lahatre.iam.',
    'prefix'     => 'v1',
    'middleware' => 'api',
], function (): void {
    /* -----------------------------------------------------------------
     | Auth endpoints
     | -----------------------------------------------------------------
     */
    Route::group([
        'as'     => 'auth.',
        'prefix' => 'auth',
    ], function (): void {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth')->name('register');

        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth')->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth')->name('forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth')->name('reset-password');

        Route::group([
            'middleware' => ['auth:sanctum', ResolveAuthContext::class],
        ], function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            // TODO: verified middleware here
            Route::post('/switch-member-role', [AuthController::class, 'switchMemberRole'])->name('switch-member-role');
        });

        Route::group([
            'middleware' => ['auth.api'],
        ], function (): void {
            Route::get('/current-permissions', [AuthController::class, 'currentPermissions'])->name('current-permissions');
        });
    });
});
