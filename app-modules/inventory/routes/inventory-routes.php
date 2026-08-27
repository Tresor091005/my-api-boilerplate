<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Inventory\Http\Controllers\InventoryItemController;
use Lahatre\Inventory\Http\Controllers\InventoryMovementController;
use Lahatre\Inventory\Http\Controllers\InventoryReadController;
use Lahatre\Inventory\Http\Controllers\InventoryStockController;
use Lahatre\Inventory\Http\Controllers\InventoryTransactionController;

Route::group([
    'as'         => 'lahatre.inventory.',
    'prefix'     => 'v1/inventory',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::get('items/{item}/locations/{location}/lots', [InventoryItemController::class, 'indexLocationLots'])->name('items.locations.lots.index');

        Route::get('stock/summary', [InventoryReadController::class, 'indexSummary'])->name('stock.summary.index');
        // TODO: add low stock endpoint when inventory thresholds are modeled.
        // Route::get('stock/low', [InventoryReadController::class, 'indexLow'])->name('stock.low.index');
        Route::get('stock/expiring', [InventoryReadController::class, 'indexExpiring'])->name('stock.expiring.index');
        Route::patch('stocks/{stock}', [InventoryStockController::class, 'update'])->name('stocks.update');

        Route::get('movements', [InventoryMovementController::class, 'index'])->name('movements.index');
        Route::get('transactions', [InventoryTransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/{transaction}', [InventoryTransactionController::class, 'show'])->name('transactions.show');
    });
});
