<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Iam\Http\Controllers\AuthController;
use Lahatre\Iam\Http\Controllers\InvitationAcceptanceController;
use Lahatre\Iam\Http\Controllers\InvitationController;
use Lahatre\Iam\Http\Controllers\MemberRoleController;
use Lahatre\Iam\Http\Controllers\OrganizationMemberController;
use Lahatre\Iam\Http\Controllers\PermissionController;
use Lahatre\Iam\Http\Controllers\RoleController;
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
        Route::post('/organization-registration-tokens', [AuthController::class, 'organizationRegistrationToken'])->middleware('throttle:auth')->name('organization-registration-tokens.store');

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

    Route::post('/iam/invitations/accept', [InvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:auth')->name('invitations.accept');

    Route::group([
        'prefix'     => 'iam',
        'middleware' => 'auth.api',
    ], function (): void {
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('organization-members', OrganizationMemberController::class)
            ->only(['index', 'show', 'update', 'destroy'])->parameters(['organization-members' => 'organizationMember']);
        Route::post('/organization-members/{organizationMember}/member-roles', [MemberRoleController::class, 'store'])->name('organization-members.member-roles.store');
        Route::delete('/organization-members/{organizationMember}/member-roles', [MemberRoleController::class, 'destroy'])->name('organization-members.member-roles.destroy');
        Route::match(['put', 'patch'], '/organization-members/{organizationMember}/member-roles', [MemberRoleController::class, 'update'])
            ->name('organization-members.member-roles.update');
        Route::apiResource('invitations', InvitationController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::put('/invitations/{invitation}/roles', [InvitationController::class, 'replaceRoles'])->name('invitations.roles.update');
        Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resendEmail'])->name('invitations.resend');
    });
});
