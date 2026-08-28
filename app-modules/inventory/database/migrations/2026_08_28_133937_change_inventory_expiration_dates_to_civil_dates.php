<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['inventory_stocks', 'inventory_movements'] as $tableName) {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN expiration_date TYPE date USING expiration_date::date");
        }
    }

    public function down(): void
    {
        foreach (['inventory_stocks', 'inventory_movements'] as $tableName) {
            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN expiration_date TYPE timestamp USING expiration_date::timestamp");
        }
    }
};
