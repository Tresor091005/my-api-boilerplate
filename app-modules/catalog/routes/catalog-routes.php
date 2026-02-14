<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([
    'as'         => 'lahatre.catalog.',
    'prefix'     => 'v1',
    'middleware' => 'api',
], function (): void {
    /* -----------------------------------------------------------------
     | Catalog endpoints
     | -----------------------------------------------------------------
     */
});
