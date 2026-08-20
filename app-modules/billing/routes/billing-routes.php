<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Billing\Http\Controllers\PlanController;

Route::group([
    'as'         => 'lahatre.billing.',
    'prefix'     => 'v1/billing',
    'middleware' => 'api',
], function (): void {
    Route::group([
        'middleware' => 'auth.api',
    ], function (): void {
        Route::apiResources([
            'plans' => PlanController::class,
        ]);
    });
});
