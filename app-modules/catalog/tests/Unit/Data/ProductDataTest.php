<?php

declare(strict_types=1);

use Lahatre\Catalog\Data\ProductData;
use Lahatre\Catalog\Data\ProductVariantData;
use Lahatre\Shared\Data\MissingValue;

it('keeps product missing fields scoped away from nested variants', function (): void {
    $product = ProductData::fromArray([
        'variants' => [[
            'unit_group_id' => 'unit-group-id',
            'options'       => [['name' => 'Color', 'value' => 'Blue']],
        ]],
    ], missingFields: ['name']);

    $variant = $product->variants->first();

    expect($product->name)->toBe(MissingValue::Instance)
        ->and($variant)->toBeInstanceOf(ProductVariantData::class)
        ->and($variant->unitGroupId)->toBe('unit-group-id')
        ->and($variant->sku)->toBeNull();
});
