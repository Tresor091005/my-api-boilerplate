<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Billing\Exceptions\FeatureUnavailableException;
use Lahatre\Inventory\Exceptions\InsufficientStockException;

it('renders module assertion exceptions as standardized json responses', function (): void {
    Route::middleware('api')->get('/_test/assertion-exception', function (): void {
        throw new InsufficientStockException(
            itemId: 'item-1',
            locationId: 'location-1',
            requested: '20',
            available: '15',
            unitCode: 'PCS',
        );
    })->name('_test.assertion-exception');

    $this->getJson('/_test/assertion-exception')
        ->assertStatus(422)
        ->assertJson([
            'message' => 'Insufficient stock for item item-1 at location location-1. Requested: 20 PCS, Available: 15 PCS.',
            'errors'  => [
                'type' => 'InsufficientStockException',
            ],
        ])
        ->assertJsonPath('errors.context.item_id', 'item-1')
        ->assertJsonPath('errors.context.location_id', 'location-1')
        ->assertJsonPath('errors.context.requested', '20')
        ->assertJsonPath('errors.context.available', '15')
        ->assertJsonPath('errors.context.unit_code', 'PCS');
});

it('renders an unavailable feature as a 403 with its reason, even in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    Route::middleware('api')->get('/_test/feature-unavailable', function (): void {
        throw FeatureUnavailableException::limitReached('organization-1', 'catalog.products', 10, 10);
    })->name('_test.feature-unavailable');

    $this->getJson('/_test/feature-unavailable')
        ->assertForbidden()
        ->assertExactJson([
            'message' => __('billing::exceptions.feature_unavailable.limit_reached', ['allowance' => 10]),
            'errors'  => [
                'type'             => 'FeatureUnavailableException',
                'reason'           => 'limit_reached',
                'feature_key'      => 'catalog.products',
                'allowance'        => 10,
                'current_quantity' => 10,
            ],
        ]);
});
