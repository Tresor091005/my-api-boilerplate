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
use Lahatre\Catalog\Http\Controllers\ProductFileController;
use Lahatre\Catalog\Http\Controllers\ProductVariantController;
use Lahatre\Catalog\Http\Controllers\ServiceController;
use Lahatre\Catalog\Http\Controllers\ServiceFileController;
use Lahatre\Catalog\Http\Controllers\StockLocationController;
use Lahatre\Catalog\Http\Controllers\StockTransferController;

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
        Route::put('products/{product}/files/main', [ProductFileController::class, 'updateMain'])->name('products.files.main.update');
        Route::post('products/{product}/files/gallery', [ProductFileController::class, 'storeGallery'])->name('products.files.gallery.store');
        Route::put('products/{product}/files/gallery', [ProductFileController::class, 'updateGallery'])->name('products.files.gallery.update');
        Route::delete('products/{product}/files/gallery', [ProductFileController::class, 'destroyGallery'])->name('products.files.gallery.destroy');
        Route::get('products/{product}/files/{attachment}/content', [ProductFileController::class, 'content'])->scopeBindings()->name('products.files.content');

        Route::put('services/{service}/files/main', [ServiceFileController::class, 'updateMain'])->name('services.files.main.update');
        Route::post('services/{service}/files/gallery', [ServiceFileController::class, 'storeGallery'])->name('services.files.gallery.store');
        Route::put('services/{service}/files/gallery', [ServiceFileController::class, 'updateGallery'])->name('services.files.gallery.update');
        Route::delete('services/{service}/files/gallery', [ServiceFileController::class, 'destroyGallery'])->name('services.files.gallery.destroy');
        Route::get('services/{service}/files/{attachment}/content', [ServiceFileController::class, 'content'])->scopeBindings()->name('services.files.content');

        Route::patch('products/{product}/variants/activation', [ProductVariantController::class, 'updateActivation'])
            ->name('products.variants.activation.update');

        Route::apiResources([
            'bundles'         => BundleController::class,
            'categories'      => CategoryController::class,
            'options'         => OptionController::class,
            'products'        => ProductController::class,
            'services'        => ServiceController::class,
            'stock-locations' => StockLocationController::class,
            'stock-transfers' => StockTransferController::class,
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

        Route::post('stock-transfers/{stockTransfer}/complete', [StockTransferController::class, 'complete'])
            ->name('stock-transfers.complete');
        Route::post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])
            ->name('stock-transfers.cancel');
    });
});
