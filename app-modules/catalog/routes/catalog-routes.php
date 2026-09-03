<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Catalog\Http\Controllers\BundleController;
use Lahatre\Catalog\Http\Controllers\BundleItemController;
use Lahatre\Catalog\Http\Controllers\BundleStockOperationController;
use Lahatre\Catalog\Http\Controllers\CategoryController;
use Lahatre\Catalog\Http\Controllers\OptionController;
use Lahatre\Catalog\Http\Controllers\OptionValueController;
use Lahatre\Catalog\Http\Controllers\ProductController;
use Lahatre\Catalog\Http\Controllers\ProductVariantController;
use Lahatre\Catalog\Http\Controllers\StockLocationController;

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
            'bundles'         => BundleController::class,
            'categories'      => CategoryController::class,
            'options'         => OptionController::class,
            'products'        => ProductController::class,
            'stock-locations' => StockLocationController::class,
        ]);

        Route::apiResource('products.variants', ProductVariantController::class)->scoped();
        Route::apiResource('options.values', OptionValueController::class)->scoped();

        Route::post('bundles/{bundle}/items', [BundleItemController::class, 'store'])
            ->name('bundles.items.store');
        Route::match(['put', 'patch'], 'bundles/{bundle}/items/{item}', [BundleItemController::class, 'update'])
            ->scopeBindings()
            ->name('bundles.items.update');
        Route::delete('bundles/{bundle}/items', [BundleItemController::class, 'destroy'])
            ->name('bundles.items.destroy');

        Route::get('bundles/{bundle}/stock-operations', [BundleStockOperationController::class, 'index'])
            ->name('bundles.stock-operations.index');
        Route::post('bundles/{bundle}/stock-operations', [BundleStockOperationController::class, 'store'])
            ->name('bundles.stock-operations.store');
        Route::get('bundles/{bundle}/stock-operations/{stockOperation}', [BundleStockOperationController::class, 'show'])
            ->scopeBindings()
            ->name('bundles.stock-operations.show');
        Route::post('bundles/{bundle}/stock-operations/{stockOperation}/complete', [BundleStockOperationController::class, 'complete'])
            ->scopeBindings()
            ->name('bundles.stock-operations.complete');
    });
});
