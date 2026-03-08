<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Catalog\Http\Controllers\CategoryController;
use Lahatre\Catalog\Http\Controllers\CurrencyController;
use Lahatre\Catalog\Http\Controllers\ProductController;
use Lahatre\Catalog\Http\Controllers\UnitController;

/* -----------------------------------------------------------------
 | Catalog endpoints
 | -----------------------------------------------------------------
 */
Route::group([
    'as'         => 'lahatre.catalog.',
    'prefix'     => 'v1/catalog',
    'middleware' => 'api',
], function (): void {
    Route::group([
        'middleware' => 'auth.api',
    ], function (): void {
        Route::apiResources([
            'categories' => CategoryController::class,
            'products'   => ProductController::class,
        ]);

        Route::get('categories/{category}/products', [CategoryController::class, 'products'])->name('categories.products');

        Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');

        Route::get('units', [UnitController::class, 'index'])->name('units.index');
        Route::post('units/sync', [UnitController::class, 'sync'])->name('units.sync');
    });
});
