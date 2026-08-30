<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Customer\Http\Controllers\CustomerAddressController;
use Lahatre\Customer\Http\Controllers\CustomerContactController;
use Lahatre\Customer\Http\Controllers\CustomerController;

Route::group([
    'as'         => 'lahatre.customer.',
    'prefix'     => 'v1/customer',
    'middleware' => 'api',
], function (): void {
    Route::group(['middleware' => 'auth.api'], function (): void {
        Route::apiResources(['customers' => CustomerController::class]);

        // addresses
        Route::post('customers/{customer}/addresses', [CustomerAddressController::class, 'store'])
            ->name('customers.addresses.store');
        Route::match(['put', 'patch'], 'customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update'])
            ->scopeBindings()
            ->name('customers.addresses.update');
        Route::delete('customers/{customer}/addresses', [CustomerAddressController::class, 'destroy'])
            ->name('customers.addresses.destroy');

        // contacts
        Route::post('customers/{customer}/contacts', [CustomerContactController::class, 'store'])
            ->name('customers.contacts.store');
        Route::match(['put', 'patch'], 'customers/{customer}/contacts/{contact}', [CustomerContactController::class, 'update'])
            ->scopeBindings()
            ->name('customers.contacts.update');
        Route::delete('customers/{customer}/contacts', [CustomerContactController::class, 'destroy'])
            ->name('customers.contacts.destroy');
    });
});
