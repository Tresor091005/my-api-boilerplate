<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Lahatre\Inventory\Data\InventoryItemFilterData;
use Lahatre\Inventory\Data\InventoryMovementFilterData;
use Lahatre\Inventory\Data\InventoryStockSummaryFilterData;
use Lahatre\Inventory\Enums\MovementType;
use Lahatre\Inventory\Http\Requests\InventoryItemFilterRequest;
use Lahatre\Inventory\Http\Requests\InventoryStockSummaryFilterRequest;

it('maps snake case filters to camel case properties with defaults', function (): void {
    $filters = InventoryItemFilterData::fromArray([
        'itemable_type'          => 'product_variant',
        'itemable_id'            => ['variant-1'],
        'stock_tracking_enabled' => false,
    ]);

    expect($filters->perPage)->toBe(15)
        ->and($filters->sortBy)->toBe('id')
        ->and($filters->sortOrder)->toBe('asc')
        ->and($filters->itemableType)->toBe('product_variant')
        ->and($filters->itemableId)->toBe(['variant-1'])
        ->and($filters->stockTrackingEnabled)->toBeFalse();
});

it('preserves array filters and converts enum and date values', function (): void {
    $summary = InventoryStockSummaryFilterData::fromArray([
        'item_id'     => ['item-1'],
        'location_id' => ['location-1'],
    ]);
    $movements = InventoryMovementFilterData::fromArray([
        'movement_type' => 'out',
        'from'          => '2026-08-01',
    ]);

    expect($summary->itemId)->toBe(['item-1'])
        ->and($summary->locationId)->toBe(['location-1'])
        ->and($movements->movementType)->toBe(MovementType::Out)
        ->and($movements->from?->toDateString())->toBe('2026-08-01');
});

it('keeps filter validation in form requests', function (): void {
    $itemRequest = new InventoryItemFilterRequest;
    $summaryRequest = new InventoryStockSummaryFilterRequest;

    expect(Validator::make(['per_page' => 0], $itemRequest->rules())->fails())->toBeTrue()
        ->and(Validator::make(['item_id' => [(string) str()->uuid()]], $summaryRequest->rules())->passes())->toBeTrue();
});
