<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Catalog\Http\Controllers\CategoryController;
use Lahatre\Catalog\Http\Controllers\OptionController;
use Lahatre\Catalog\Http\Controllers\OptionValueController;
use Lahatre\Catalog\Http\Controllers\ProductController;

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
            'options'    => OptionController::class,
            'products'   => ProductController::class,
        ]);
        Route::apiResource('options.values', OptionValueController::class)
            ->parameters(['values' => 'optionValue']);
    });
});
