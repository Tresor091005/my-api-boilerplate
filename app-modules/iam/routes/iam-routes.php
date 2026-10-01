<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Iam\Http\Controllers\AuthController;
use Lahatre\Iam\Http\Controllers\EmailLoginController;
use Lahatre\Iam\Http\Controllers\InvitationAcceptanceController;
use Lahatre\Iam\Http\Controllers\InvitationController;
use Lahatre\Iam\Http\Controllers\MemberRoleController;
use Lahatre\Iam\Http\Controllers\OrganizationMemberController;
use Lahatre\Iam\Http\Controllers\PermissionController;
use Lahatre\Iam\Http\Controllers\RoleController;
use Lahatre\Iam\Http\Controllers\SessionController;
use Lahatre\Iam\Http\Middleware\ResolveAuthContext;
use Lahatre\Iam\Http\Middleware\TrackSessionActivity;

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
        Route::post('/organization-registrations', [AuthController::class, 'registerOrganization'])->middleware('throttle:auth')->name('organization-registrations.store');
        Route::post('/organization-registration-tokens', [AuthController::class, 'organizationRegistrationToken'])->middleware('throttle:auth')->name('organization-registration-tokens.store');

        Route::post('/email-challenges', [EmailLoginController::class, 'store'])->middleware('throttle:auth')->name('email-challenges.store');
        Route::post('/email-challenge-verifications', [EmailLoginController::class, 'verify'])->middleware('throttle:auth')->name('email-challenge-verifications.store');

        Route::group([
            'middleware' => ['auth:sanctum', TrackSessionActivity::class, ResolveAuthContext::class.':user'],
        ], function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
            Route::delete('/sessions', [SessionController::class, 'destroyAll'])->name('sessions.destroy-all');
            Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->whereNumber('session')->name('sessions.destroy');
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
