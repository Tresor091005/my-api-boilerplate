<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Inventory\Http\Controllers\InventoryMovementController;
use Lahatre\Inventory\Http\Controllers\InventoryStockController;
use Lahatre\Inventory\Http\Controllers\InventoryTransactionController;

Route::group([
    'as'         => 'lahatre.inventory.',
    'prefix'     => 'v1/inventory',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::get('items/{item}/locations/{location}/lots', [InventoryStockController::class, 'indexLocationLots'])->name('items.locations.lots.index');

        Route::get('stocks', [InventoryStockController::class, 'index'])->name('stocks.index');
        Route::get('stocks/summary', [InventoryStockController::class, 'indexSummary'])->name('stocks.summary.index');
        // TODO: add low stock filters when inventory thresholds are modeled.
        Route::patch('stocks/{stock}', [InventoryStockController::class, 'update'])->name('stocks.update');

        Route::get('movements', [InventoryMovementController::class, 'index'])->name('movements.index');
        Route::get('transactions', [InventoryTransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/{transaction}', [InventoryTransactionController::class, 'show'])->name('transactions.show');
    });
});
