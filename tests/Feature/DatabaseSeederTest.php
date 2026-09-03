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
    $this->assertDatabaseCount('catalog_stock_locations', 2);
    $this->assertDatabaseCount('inventory_locations', 2);
    $this->assertDatabaseCount('customer_customers', 3);
    $this->assertDatabaseCount('inventory_transactions', 1);
});
