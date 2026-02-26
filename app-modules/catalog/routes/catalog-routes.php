<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Catalog\Http\Controllers\CategoryController;
use Lahatre\Catalog\Http\Controllers\ProductController;
use Lahatre\Catalog\Http\Controllers\TagController;

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
            'tags'       => TagController::class,
        ]);

        Route::get('categories/{category}/products', [CategoryController::class, 'viewProducts'])->name('categories.view-products');
    });
});
