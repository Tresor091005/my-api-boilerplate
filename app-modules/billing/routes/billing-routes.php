<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Billing\Http\Controllers\CheckoutController;
use Lahatre\Billing\Http\Controllers\FeatureController;
use Lahatre\Billing\Http\Controllers\OfferController;
use Lahatre\Billing\Http\Controllers\PlanController;
use Lahatre\Billing\Http\Controllers\PlanDraftController;
use Lahatre\Billing\Http\Controllers\PlanLifecycleController;
use Lahatre\Billing\Http\Controllers\SubscriptionController;
use Lahatre\Billing\Http\Controllers\TrialController;

Route::group([
    'as'         => 'lahatre.billing.',
    'prefix'     => 'v1/billing',
    'middleware' => 'api',
], function (): void {
    Route::get('offers', [OfferController::class, 'index'])->name('offers.index');

    Route::group([
        'middleware' => 'auth.api',
    ], function (): void {
        Route::get('subscription', [SubscriptionController::class, 'show'])->name('subscription.show')->withoutMiddleware('billing.subscription');
        Route::get('trial', [TrialController::class, 'show'])->name('trial.show')->withoutMiddleware('billing.subscription');
        Route::apiResource('checkouts', CheckoutController::class)
            ->only(['index', 'store', 'show'])
            ->withoutMiddleware('billing.subscription');
    });

    Route::group([
        'middleware' => 'auth.platform',
    ], function (): void {
        Route::apiResources([
            'plans' => PlanController::class,
        ]);
        Route::patch('plans/{plan}/draft', [PlanDraftController::class, 'update'])->name('plans.draft.update');
        Route::delete('plans/{plan}/draft', [PlanDraftController::class, 'destroy'])->name('plans.draft.destroy');
        Route::post('plans/{plan}/draft/features', [PlanDraftController::class, 'addFeatures'])->name('plans.draft.features.add');
        Route::delete('plans/{plan}/draft/features', [PlanDraftController::class, 'removeFeatures'])->name('plans.draft.features.remove');
        Route::post('plans/{plan}/publish', [PlanLifecycleController::class, 'publish'])->name('plans.publish');
        Route::post('plans/{plan}/archive', [PlanLifecycleController::class, 'archive'])->name('plans.archive');
        Route::get('plans/{plan}/versions', [PlanLifecycleController::class, 'versions'])->name('plans.versions');
        Route::get('features', [FeatureController::class, 'index'])->name('features.index');
    });
});
