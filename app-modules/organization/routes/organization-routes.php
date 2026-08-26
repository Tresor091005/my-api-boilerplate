<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Organization\Http\Controllers\OrganizationSettingsController;

Route::group([
    'as'         => 'lahatre.organization.',
    'prefix'     => 'v1/organization',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::get('settings', [OrganizationSettingsController::class, 'show'])->name('settings.show');
        Route::patch('settings', [OrganizationSettingsController::class, 'update'])->name('settings.update');
    });
});
