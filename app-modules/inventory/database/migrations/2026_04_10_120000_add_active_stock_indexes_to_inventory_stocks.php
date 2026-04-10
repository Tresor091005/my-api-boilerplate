<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    public function up(): void
    {
        // Read paths frequently filter `remaining > 0` (active stocks). Partial indexes keep them small.
        DB::statement(
            'CREATE INDEX IF NOT EXISTS inventory_stocks_item_id_location_id_active_index
             ON inventory_stocks (item_id, location_id)
             WHERE deleted_at IS NULL AND remaining > 0'
        );

        DB::statement(
            'CREATE INDEX IF NOT EXISTS inventory_stocks_location_id_item_id_active_index
             ON inventory_stocks (location_id, item_id)
             WHERE deleted_at IS NULL AND remaining > 0'
        );

        DB::statement(
            'CREATE INDEX IF NOT EXISTS inventory_stocks_expiration_date_active_index
             ON inventory_stocks (expiration_date)
             WHERE deleted_at IS NULL AND remaining > 0 AND expiration_date IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_stocks_item_id_location_id_active_index');
        DB::statement('DROP INDEX IF EXISTS inventory_stocks_location_id_item_id_active_index');
        DB::statement('DROP INDEX IF EXISTS inventory_stocks_expiration_date_active_index');
    }
};
