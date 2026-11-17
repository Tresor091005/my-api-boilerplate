<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Sale\Http\Controllers\SaleController;
use Lahatre\Sale\Http\Controllers\SaleSettingController;

Route::group([
    'as'         => 'lahatre.sale.',
    'prefix'     => 'v1/sale',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::get('settings', [SaleSettingController::class, 'show'])->name('settings.show');
        Route::put('settings', [SaleSettingController::class, 'update'])->name('settings.update');
        // a draft is replaced whole, and a sale is never deleted
        Route::apiResource('sales', SaleController::class)->except(['update', 'destroy']);
        Route::put('sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
        Route::post('sales/{sale}/validate', [SaleController::class, 'validate'])->name('sales.validate');
        Route::post('sales/{sale}/approve', [SaleController::class, 'approve'])->name('sales.approve');
        Route::post('sales/{sale}/reject', [SaleController::class, 'reject'])->name('sales.reject');
        Route::post('sales/{sale}/reopen', [SaleController::class, 'reopen'])->name('sales.reopen');
        Route::post('sales/{sale}/abandon', [SaleController::class, 'abandon'])->name('sales.abandon');
        Route::post('sales/{sale}/confirm', [SaleController::class, 'confirm'])->name('sales.confirm');
        Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
        Route::post('sales/{sale}/resume', [SaleController::class, 'resume'])->name('sales.resume');
    });
});
