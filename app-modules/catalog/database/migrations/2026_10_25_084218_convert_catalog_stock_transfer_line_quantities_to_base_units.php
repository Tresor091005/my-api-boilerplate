<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stock transfer lines stored their quantity in the unit it was entered in. Like bundle items, they now
 * store it in base units and keep `display_unit_code` for display. A base unit has ratio 1, so the base
 * quantity is the entered quantity times the unit's ratio.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE catalog_stock_transfer_lines AS lines
            SET quantity = lines.quantity * units.ratio
            FROM master_units AS units
            WHERE units.code = lines.display_unit_code
            SQL);

        // same length as master_units.code and catalog_bundle_items.display_unit_code
        DB::statement('ALTER TABLE catalog_stock_transfer_lines ALTER COLUMN display_unit_code TYPE varchar(255)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE catalog_stock_transfer_lines ALTER COLUMN display_unit_code TYPE varchar(100)');

        DB::statement(<<<'SQL'
            UPDATE catalog_stock_transfer_lines AS lines
            SET quantity = lines.quantity / units.ratio
            FROM master_units AS units
            WHERE units.code = lines.display_unit_code
            SQL);
    }
};
