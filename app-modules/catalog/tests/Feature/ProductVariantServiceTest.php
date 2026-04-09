<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\DTO\ProductVariantDataDTO;
use Lahatre\Catalog\DTO\ProductVariantDTO;
use Lahatre\Catalog\DTO\ProductVariantFilterDTO;
use Lahatre\Catalog\DTO\ProductVariantUpdateDTO;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Services\ProductVariantService;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Inventory\Models\InventoryLocation;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RateLimiter::for('api', fn () => Limit::none());

    $this->unitGroup = UnitGroup::factory()->create(['name' => 'Piece']);
    Unit::factory()->create([
        'group_id' => $this->unitGroup->id,
        'ratio'    => 1,
        'code'     => 'PCS',
        'name'     => 'Piece',
        'symbol'   => 'pc',
    ]);

    $this->otherUnitGroup = UnitGroup::factory()->create(['name' => 'Box']);
    Unit::factory()->create([
        'group_id' => $this->otherUnitGroup->id,
        'ratio'    => 1,
        'code'     => 'BOX',
        'name'     => 'Box',
        'symbol'   => 'bx',
    ]);

    $this->currency = Currency::factory()->create();
});

it('manages product variants through the service flow', function (): void {
    $service = app(ProductVariantService::class);
    $product = Product::factory()->create(['name' => 'T-Shirt']);
    $variant = ProductVariant::factory()->create([
        'product_id'    => $product->id,
        'unit_group_id' => $this->unitGroup->id,
    ]);
    InventoryItem::factory()->create([
        'itemable_type'  => $variant->getMorphClass(),
        'itemable_id'    => $variant->id,
        'sku'            => $variant->sku,
        'base_unit_code' => 'PCS',
        'is_active'      => true,
    ]);

    $listResponse = $service->list($product, ProductVariantFilterDTO::fromArray([]))->toResponse(
        Request::create("/v1/catalog/products/{$product->id}/variants", 'GET')
    );

    expect($listResponse->getStatusCode())->toBe(200)
        ->and($listResponse->getData(true)['data'][0]['id'])->toBe($variant->id);

    $showPayload = $service->retrieve($product, $variant)->toArray(
        Request::create("/v1/catalog/products/{$product->id}/variants/{$variant->id}", 'GET')
    );

    expect($showPayload['id'])->toBe($variant->id)
        ->and($showPayload['product_id'])->toBe($product->id)
        ->and($showPayload['inventory']['id'])->not->toBeNull()
        ->and($showPayload['inventory']['total_remaining'])->toBe(0)
        ->and($showPayload['inventory']['active_lots_count'])->toBe(0);

    $createdPayload = $service->create($product, ProductVariantDTO::fromArray([
        'variants' => [
            [
                'sku'                 => 'tee-blue-m',
                'unit_group_id'       => $this->unitGroup->id,
                'should_manage_stock' => true,
                'is_active'           => true,
                'options'             => [
                    ['name' => 'Color', 'value' => 'Blue'],
                    ['name' => 'Size', 'value' => 'M'],
                ],
            ],
        ],
    ]))->toArray(
        Request::create("/v1/catalog/products/{$product->id}/variants", 'POST')
    );

    expect($createdPayload[0]['product_id'])->toBe($product->id)
        ->and($createdPayload[0]['sku'])->toBe('TEE-BLUE-M')
        ->and($createdPayload[0]['options']['color'])->toBe('blue')
        ->and($createdPayload[0]['options']['size'])->toBe('m');

    $createdVariantId = (string) $createdPayload[0]['id'];

    $updatedPayload = $service->update($product, ProductVariant::query()->findOrFail($createdVariantId), ProductVariantUpdateDTO::fromArray([
        'sku'                 => 'tee-blue-l',
        'should_manage_stock' => false,
        'is_active'           => false,
        'options'             => [
            ['name' => 'Color', 'value' => 'Blue'],
            ['name' => 'Size', 'value' => 'L'],
        ],
    ]))->toArray(
        Request::create("/v1/catalog/products/{$product->id}/variants/{$createdVariantId}", 'PUT')
    );

    expect($updatedPayload['id'])->toBe($createdVariantId)
        ->and($updatedPayload['sku'])->toBe('TEE-BLUE-L')
        ->and($updatedPayload['unit_group_id'])->toBe($this->unitGroup->id)
        ->and($updatedPayload['should_manage_stock'])->toBeFalse()
        ->and($updatedPayload['is_active'])->toBeFalse()
        ->and($updatedPayload['options']['color'])->toBe('blue')
        ->and($updatedPayload['options']['size'])->toBe('l')
        ->and($updatedPayload['inventory']['sku'])->toBe('TEE-BLUE-L')
        ->and($updatedPayload['inventory']['total_remaining'])->toBe(0);

    expect(ProductVariant::query()->findOrFail($createdVariantId)->sku)
        ->toBe('TEE-BLUE-L');

    $inventoryItem = InventoryItem::query()
        ->where('itemable_type', ProductVariant::query()->findOrFail($createdVariantId)->getMorphClass())
        ->where('itemable_id', $createdVariantId)
        ->first();

    expect($inventoryItem)->not->toBeNull()
        ->and($inventoryItem?->sku)->toBe('TEE-BLUE-L')
        ->and($inventoryItem?->is_active)->toBeFalse();
});

it('embeds aggregated inventory summary for a variant when active stocks exist', function (): void {
    $service = app(ProductVariantService::class);
    $product = Product::factory()->create(['name' => 'Flour']);
    $variant = ProductVariant::factory()->create([
        'product_id'    => $product->id,
        'unit_group_id' => $this->unitGroup->id,
        'sku'           => 'FAR-001',
    ]);

    $inventoryItem = InventoryItem::factory()->create([
        'itemable_type'      => $variant->getMorphClass(),
        'itemable_id'        => $variant->id,
        'sku'                => $variant->sku,
        'base_unit_code'     => 'PCS',
        'deduction_strategy' => null,
        'is_active'          => true,
    ]);

    $location = InventoryLocation::factory()->create();

    InventoryStock::factory()->for($inventoryItem, 'item')->for($location, 'location')->create([
        'unit_code'     => 'PCS',
        'currency_code' => $this->currency->code,
        'quantity'      => 50,
        'remaining'     => 50,
    ]);

    InventoryStock::factory()->for($inventoryItem, 'item')->for($location, 'location')->create([
        'unit_code'     => 'PCS',
        'currency_code' => $this->currency->code,
        'quantity'      => 20,
        'remaining'     => 0,
    ]);

    $payload = $service->retrieve($product, $variant)->toArray(
        Request::create("/v1/catalog/products/{$product->id}/variants/{$variant->id}", 'GET')
    );

    expect($payload['inventory']['id'])->toBe($inventoryItem->id)
        ->and($payload['inventory']['sku'])->toBe('FAR-001')
        ->and($payload['inventory']['base_unit_code'])->toBe('PCS')
        ->and($payload['inventory']['total_remaining'])->toBe(50)
        ->and($payload['inventory']['active_lots_count'])->toBe(1);
});

it('returns not found when a variant does not belong to the given product', function (): void {
    $service = app(ProductVariantService::class);
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id'    => $otherProduct->id,
        'unit_group_id' => $this->unitGroup->id,
    ]);

    expect(fn () => $service->retrieve($product, $variant))
        ->toThrow(ModelNotFoundException::class);
});

it('validates duplicate option names when creating a product variant', function (): void {
    expect(fn () => ProductVariantDataDTO::fromArray([
        'sku'                 => 'variant-with-duplicates',
        'unit_group_id'       => $this->unitGroup->id,
        'should_manage_stock' => true,
        'is_active'           => true,
        'options'             => [
            ['name' => 'Color', 'value' => 'Blue'],
            ['name' => 'color', 'value' => 'Red'],
        ],
    ]))->toThrow(ValidationException::class);
});
