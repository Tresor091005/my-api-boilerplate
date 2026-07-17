<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Inventory\Http\Controllers\InventoryItemController;
use Lahatre\Inventory\Http\Controllers\InventoryLocationController;
use Lahatre\Inventory\Http\Controllers\InventoryReadController;
use Lahatre\Inventory\Http\Controllers\InventoryTransactionController;

Route::group([
    'as'         => 'lahatre.inventory.',
    'prefix'     => 'v1/inventory',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::get('items', [InventoryItemController::class, 'index'])->name('items.index');
        Route::get('items/{item}', [InventoryItemController::class, 'show'])->name('items.show');
        Route::get('items/{item}/stock', [InventoryItemController::class, 'showStock'])->name('items.stock.show');
        Route::get('items/{item}/value', [InventoryItemController::class, 'showValue'])->name('items.value.show');
        Route::get('items/{item}/locations/{location}/lots', [InventoryItemController::class, 'indexLocationLots'])->name('items.locations.lots.index');
        Route::get('items/{item}/movements', [InventoryItemController::class, 'indexMovements'])->name('items.movements.index');

        Route::get('locations', [InventoryLocationController::class, 'index'])->name('locations.index');
        Route::get('locations/{location}', [InventoryLocationController::class, 'show'])->name('locations.show');
        Route::get('locations/{location}/stock', [InventoryLocationController::class, 'showStock'])->name('locations.stock.show');
        Route::get('locations/{location}/value', [InventoryLocationController::class, 'showValue'])->name('locations.value.show');
        Route::get('locations/{location}/movements', [InventoryLocationController::class, 'indexMovements'])->name('locations.movements.index');

        Route::get('stock/summary', [InventoryReadController::class, 'indexSummary'])->name('stock.summary.index');
        // TODO: add low stock endpoint when inventory thresholds are modeled.
        // Route::get('stock/low', [InventoryReadController::class, 'indexLow'])->name('stock.low.index');
        Route::get('stock/expiring', [InventoryReadController::class, 'indexExpiring'])->name('stock.expiring.index');

        Route::get('transactions', [InventoryTransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/{transaction}', [InventoryTransactionController::class, 'show'])->name('transactions.show');
    });
});
