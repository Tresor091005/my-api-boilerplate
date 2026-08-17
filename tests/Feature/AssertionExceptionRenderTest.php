<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lahatre\Inventory\Exceptions\InsufficientStockException;
use Lahatre\Shared\Registries\ResponseContractRegistry;

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

    app(ResponseContractRegistry::class)->registerMany([
        '_test.assertion-exception' => [],
    ]);

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
