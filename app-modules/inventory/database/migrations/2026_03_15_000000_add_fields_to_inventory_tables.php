<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->string('sku')->nullable()->unique();
            $table->string('base_unit_code');
            $table->boolean('is_active')->default(true);

            $table->foreign('base_unit_code')
                ->references('code')
                ->on('master_units')
                ->onDelete('restrict');
        });

        DB::statement('ALTER TABLE inventory_stocks ADD CONSTRAINT inventory_stocks_remaining_check_non_negative CHECK (remaining >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_stocks DROP CONSTRAINT inventory_stocks_remaining_check_non_negative');

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropForeign(['base_unit_code']);
            $table->dropColumn(['sku', 'base_unit_code', 'is_active']);
        });

        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
