<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\Models\Product; // TODO: violation
use Lahatre\Inventory\Enums\DeductionStrategy;
use Lahatre\Inventory\Enums\TransactionType;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Inventory\Services\InventoryService;
use Lahatre\Inventory\Tests\Fixtures\TestInventoryCompany;
use Lahatre\Inventory\Tests\Fixtures\TestInventoryVariant;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(InventoryService::class);

    // Setup Master Data
    $this->currency = Currency::factory()->create();
    $this->group = UnitGroup::factory()->create();
    $this->unit = Unit::factory()->create(['ratio' => 1, 'group_id' => $this->group->id]);

    // Setup Inventory Data
    $this->item = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $this->location = InventoryLocation::factory()->create();
});

it('fails if multiple currencies are used in one transaction', function (): void {
    $currency2 = Currency::factory()->create();
    $item2 = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);

    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            ['type' => 'in', 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 10, 'unit_code' => $this->unit->code, 'unit_cost' => 10, 'currency_code' => $this->currency->code],
            ['type' => 'in', 'item_id' => $item2->id, 'location_id' => $this->location->id, 'quantity' => 5, 'unit_code' => $this->unit->code, 'unit_cost' => 10, 'currency_code' => $currency2->code],
        ],
    ];

    $this->service->recordTransaction($payload);
})->throws(ValidationException::class, 'All movements in a transaction must use the same currency code.');

it('fails if unit does not belong to the same group as item base unit', function (): void {
    $otherGroup = UnitGroup::factory()->create();
    $otherUnit = Unit::factory()->create(['group_id' => $otherGroup->id]);

    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            ['type' => 'in', 'item_id' => $this->item->id, 'location_id' => $this->location->id, 'quantity' => 10, 'unit_code' => $otherUnit->code, 'unit_cost' => 10, 'currency_code' => $this->currency->code],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, "Unit {$otherUnit->code} belongs to a different group than item base unit {$this->item->base_unit_code}.");
});

it('fails if manual strategy is used without providing stock_ids', function (): void {
    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'type'        => 'out',
                'item_id'     => $this->item->id,
                'location_id' => $this->location->id,
                'quantity'    => 10,
                'unit_code'   => $this->unit->code,
                'strategy'    => DeductionStrategy::Manual->value,
                // stock_ids are missing
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, 'Stock IDs are required when strategy is manual.');
});

it('fails if a provided stock_id does not belong to the correct item and location', function (): void {
    $otherItem = InventoryItem::factory()->create(['base_unit_code' => $this->unit->code]);
    $stockForOtherItem = InventoryStock::factory()->for($otherItem, 'item')->for($this->location, 'location')->create();

    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [
            [
                'type'        => 'out',
                'item_id'     => $this->item->id, // This item
                'location_id' => $this->location->id,
                'quantity'    => 10,
                'unit_code'   => $this->unit->code,
                'strategy'    => DeductionStrategy::Manual->value,
                'stock_ids'   => [$stockForOtherItem->id], // But stock from other item
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, "Stock ID {$stockForOtherItem->id} does not belong to the correct item and location.");
});

it('fails when a resolved inventory item is inactive', function (): void {
    config()->set('inventory.enable_model_reference_preprocessing', true);

    $variant = TestInventoryVariant::query()->create([
        'product_id'          => Product::factory()->create()->id,
        'sku'                 => fake()->unique()->bothify('SKU-####-????'),
        'unit_group_id'       => $this->group->id,
        'should_manage_stock' => true,
        'is_active'           => true,
    ]);

    $company = TestInventoryCompany::query()->create([
        'name' => fake()->company(),
    ]);

    InventoryItem::factory()->create([
        'itemable_type'  => $variant->getMorphClass(),
        'itemable_id'    => $variant->getKey(),
        'base_unit_code' => $this->unit->code,
        'is_active'      => false,
    ]);

    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'type'          => 'in',
                'item'          => $variant,
                'location'      => $company,
                'quantity'      => 10,
                'unit_code'     => $this->unit->code,
                'unit_cost'     => 10,
                'currency_code' => $this->currency->code,
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, 'The selected item is invalid or inactive.');
});

it('fails when a resolved inventory location is inactive', function (): void {
    config()->set('inventory.enable_model_reference_preprocessing', true);

    $variant = TestInventoryVariant::query()->create([
        'product_id'          => Product::factory()->create()->id,
        'sku'                 => fake()->unique()->bothify('SKU-####-????'),
        'unit_group_id'       => $this->group->id,
        'should_manage_stock' => true,
        'is_active'           => true,
    ]);

    $company = TestInventoryCompany::query()->create([
        'name' => fake()->company(),
    ]);

    InventoryLocation::factory()->create([
        'external_type' => $company->getMorphClass(),
        'external_id'   => $company->getKey(),
        'is_active'     => false,
    ]);

    $payload = [
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'type'          => 'in',
                'item'          => $variant,
                'location'      => $company,
                'quantity'      => 10,
                'unit_code'     => $this->unit->code,
                'unit_cost'     => 10,
                'currency_code' => $this->currency->code,
            ],
        ],
    ];

    expect(fn () => $this->service->recordTransaction($payload))
        ->toThrow(ValidationException::class, 'The selected location is invalid or inactive.');
});

it('does not persist resolved references when preprocessing is enabled but validation fails', function (): void {
    config()->set('inventory.enable_model_reference_preprocessing', true);

    $variant = TestInventoryVariant::query()->create([
        'product_id'          => Product::factory()->create()->id,
        'sku'                 => fake()->unique()->bothify('SKU-####-????'),
        'unit_group_id'       => $this->group->id,
        'should_manage_stock' => true,
        'is_active'           => true,
    ]);

    $company = TestInventoryCompany::query()->create([
        'name' => fake()->company(),
    ]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [
            [
                'type'      => 'in',
                'item'      => $variant,
                'location'  => $company,
                'quantity'  => 10,
                'unit_code' => $this->unit->code,
                'unit_cost' => 10,
                // currency_code intentionally missing
            ],
        ],
    ]))->toThrow(ValidationException::class);

    expect(InventoryItem::query()
        ->where('itemable_type', $variant->getMorphClass())
        ->where('itemable_id', $variant->getKey())
        ->exists())->toBeFalse()
        ->and(InventoryLocation::query()
            ->where('external_type', $company->getMorphClass())
            ->where('external_id', $company->getKey())
            ->exists())->toBeFalse();
});

it('fails validation if Manual strategy is resolved via Item settings but stock_ids are missing', function (): void {
    $this->item->update(['deduction_strategy' => DeductionStrategy::Manual]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'type'        => 'out',
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 10,
            'unit_code'   => $this->unit->code,
        ]],
    ]))->toThrow(ValidationException::class, 'Stock IDs are required when strategy is manual.');
});

it('fails validation if Manual strategy is resolved via Global config but stock_ids are missing', function (): void {
    config()->set('inventory.default_strategy', DeductionStrategy::Manual->value);
    $this->item->update(['deduction_strategy' => null]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::Out->value,
        'movements'        => [[
            'type'        => 'out',
            'item_id'     => $this->item->id,
            'location_id' => $this->location->id,
            'quantity'    => 10,
            'unit_code'   => $this->unit->code,
        ]],
    ]))->toThrow(ValidationException::class, 'Stock IDs are required when strategy is manual.');
});

it('fails transaction if the selected item is inactive', function (): void {
    $this->item->update(['is_active' => false]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'unit_cost'     => 10,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(ValidationException::class, 'The selected item is invalid or inactive.');
});

it('fails transaction if the selected location is inactive', function (): void {
    $this->location->update(['is_active' => false]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'unit_cost'     => 10,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(ValidationException::class, 'The selected location is invalid or inactive.');
});

it('fails if the item base unit has a ratio different than 1', function (): void {
    $invalidBaseUnit = Unit::factory()->create(['ratio' => 2, 'group_id' => $this->group->id]);
    $item = InventoryItem::factory()->create(['base_unit_code' => $invalidBaseUnit->code]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $invalidBaseUnit->code,
            'unit_cost'     => 10,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(\Exception::class, 'must have a ratio of 1');
});

it('fails if unit_cost has more decimal places than allowed by the currency', function (): void {
    $currency = Currency::factory()->create(['precision' => 2]);

    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'unit_cost'     => '10.123',
            'currency_code' => $currency->code,
        ]],
    ]))->toThrow(ValidationException::class, "must have at most {$currency->precision} decimal places");
});

it('fails if unit_cost is negative', function (): void {
    expect(fn () => $this->service->recordTransaction([
        'reference_type'   => 'test',
        'reference_id'     => '123',
        'transaction_type' => TransactionType::In->value,
        'movements'        => [[
            'type'          => 'in',
            'item_id'       => $this->item->id,
            'location_id'   => $this->location->id,
            'quantity'      => 10,
            'unit_code'     => $this->unit->code,
            'unit_cost'     => -1,
            'currency_code' => $this->currency->code,
        ]],
    ]))->toThrow(ValidationException::class);
});
