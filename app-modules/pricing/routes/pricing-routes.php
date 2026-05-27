<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/* -----------------------------------------------------------------
 | Pricing endpoints
 | -----------------------------------------------------------------
 */
Route::group([
    'as'         => 'lahatre.pricing.',
    'prefix'     => 'v1/pricing',
    'middleware' => 'api',
], function (): void {
    //
});
