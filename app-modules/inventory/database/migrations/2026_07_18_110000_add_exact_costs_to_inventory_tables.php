<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->bigInteger('cost_remainder')->default(0)->after('unit_cost');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->bigInteger('total_cost')->default(0)->after('unit_cost');
        });

        DB::statement('UPDATE inventory_movements SET total_cost = quantity * unit_cost');
        DB::statement('ALTER TABLE inventory_stocks ADD CONSTRAINT inventory_stocks_cost_remainder_check_non_negative CHECK (cost_remainder >= 0)');
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_total_cost_check_non_negative CHECK (total_cost >= 0)');
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_movements DROP CONSTRAINT inventory_movements_total_cost_check_non_negative');
        DB::statement('ALTER TABLE inventory_stocks DROP CONSTRAINT inventory_stocks_cost_remainder_check_non_negative');

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->bigInteger('unit_cost')->default(0)->after('unit_code');
        });

        DB::statement('UPDATE inventory_movements SET unit_cost = total_cost / NULLIF(quantity, 0)');

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn('total_cost');
        });

        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropColumn('cost_remainder');
        });
    }
};
