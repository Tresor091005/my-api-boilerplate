<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Models\StockTransferLine;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Master\Models\Unit;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
});

it('converts the stored line quantities to base units and back', function (): void {
    $line = StockTransferLine::factory()->create();
    $unit = Unit::query()->where('code', $line->display_unit_code)->firstOrFail();
    $carton = Unit::factory()->create(['organization_id' => null, 'group_id' => $unit->group_id, 'ratio' => 10]);
    // a line written before the change: 2 cartons stored as entered
    $line->forceFill(['quantity' => 2, 'display_unit_code' => $carton->code])->save();

    $migration = require base_path('app-modules/catalog/database/migrations/2026_10_25_084218_convert_catalog_stock_transfer_line_quantities_to_base_units.php');

    if (!is_object($migration) || !method_exists($migration, 'up') || !method_exists($migration, 'down')) {
        throw new LogicException('The migration file does not return a migration.');
    }

    $migration->up();
    expect($line->refresh()->quantity)->toBe(20);

    $migration->down();
    expect($line->refresh()->quantity)->toBe(2);
});
