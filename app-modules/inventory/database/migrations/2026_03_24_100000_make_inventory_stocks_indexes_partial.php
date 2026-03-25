<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropIndex('inventory_stocks_item_id_index');
            $table->dropIndex('inventory_stocks_location_id_index');
            $table->dropIndex('inventory_stocks_currency_code_index');
            $table->dropIndex('inventory_stocks_unit_code_index');
        });

        DB::statement('CREATE INDEX inventory_stocks_item_id_index ON inventory_stocks (item_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX inventory_stocks_location_id_index ON inventory_stocks (location_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX inventory_stocks_currency_code_index ON inventory_stocks (currency_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX inventory_stocks_unit_code_index ON inventory_stocks (unit_code) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropIndex('inventory_stocks_item_id_index');
            $table->dropIndex('inventory_stocks_location_id_index');
            $table->dropIndex('inventory_stocks_currency_code_index');
            $table->dropIndex('inventory_stocks_unit_code_index');

            $table->index('item_id');
            $table->index('location_id');
            $table->index('currency_code');
            $table->index('unit_code');
        });
    }
};
