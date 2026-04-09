<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Inventory\Http\Controllers\InventoryReadController;

Route::group([
    'as'         => 'lahatre.inventory.',
    'prefix'     => 'v1/inventory',
    'middleware' => 'api',
], function (): void {
    Route::get('items/{item}/stock', [InventoryReadController::class, 'showItemStock'])->name('items.stock.show');
    Route::get('locations/{location}/stock', [InventoryReadController::class, 'showLocationStock'])->name('locations.stock.show');

    Route::get('items/{item}/locations/{location}/lots', [InventoryReadController::class, 'indexItemLocationLots'])->name('items.locations.lots.index');

    Route::get('stock/summary', [InventoryReadController::class, 'indexSummary'])->name('stock.summary.index');
    // TODO: add low stock endpoint when inventory thresholds are modeled.
    // Route::get('stock/low', [InventoryReadController::class, 'indexLow'])->name('stock.low.index');
    Route::get('stock/expiring', [InventoryReadController::class, 'indexExpiring'])->name('stock.expiring.index');

    Route::get('items/{item}/movements', [InventoryReadController::class, 'indexItemMovements'])->name('items.movements.index');
    Route::get('locations/{location}/movements', [InventoryReadController::class, 'indexLocationMovements'])->name('locations.movements.index');
    Route::get('transactions/{transaction}', [InventoryReadController::class, 'showTransaction'])->name('transactions.show');
});
