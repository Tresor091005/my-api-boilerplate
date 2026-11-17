<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('seeds a coherent and idempotent development dataset', function (): void {
    expect(Artisan::call('db:seed', [
        '--class'          => DatabaseSeeder::class,
        '--no-interaction' => true,
    ]))->toBe(0);
    expect(Artisan::call('db:seed', [
        '--class'          => DatabaseSeeder::class,
        '--no-interaction' => true,
    ]))->toBe(0);

    $this->assertDatabaseHas('organization_organizations', [
        'name'                     => 'kouri',
        'functional_currency_code' => 'XOF',
    ]);
    $this->assertDatabaseCount('catalog_products', 6);
    $this->assertDatabaseCount('catalog_product_variants', 9);
    $this->assertDatabaseCount('catalog_bundles', 4);
    $this->assertDatabaseCount('catalog_items', 13);
    $this->assertDatabaseCount('inventory_items', 13);
    // the two warehouses, and the hold location of the one the bill-and-hold sale set goods aside in
    $this->assertDatabaseCount('catalog_stock_locations', 3);
    $this->assertDatabaseCount('inventory_locations', 3);
    $this->assertDatabaseCount('customer_customers', 4);
    $this->assertDatabaseHas('customer_customers', ['is_walk_in' => true]);
    $this->assertDatabaseCount('inventory_transactions', 2);
    $this->assertDatabaseHas('sale_sales', ['mode' => 'bill_and_hold', 'status' => 'active']);
    $this->assertDatabaseCount('sale_holds', 1);
});
