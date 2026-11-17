<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_stock_locations', function (Blueprint $table): void {
            $table->string('kind', 20)->default('warehouse');
            $table->uuid('held_for_location_id')->nullable();
            $table->foreign(['organization_id', 'held_for_location_id'])
                ->references(['organization_id', 'id'])->on('catalog_stock_locations')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE catalog_stock_locations ADD CONSTRAINT catalog_stock_locations_kind_check CHECK (kind IN ('warehouse', 'customer_hold'))");
        DB::statement("ALTER TABLE catalog_stock_locations ADD CONSTRAINT catalog_stock_locations_held_for_check CHECK ((kind = 'customer_hold') = (held_for_location_id IS NOT NULL))");
        DB::statement('CREATE UNIQUE INDEX catalog_stock_locations_held_for_location_id_unique ON catalog_stock_locations (organization_id, held_for_location_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::table('catalog_stock_locations')->where('kind', 'customer_hold')->delete();
        DB::statement('DROP INDEX IF EXISTS catalog_stock_locations_held_for_location_id_unique');
        DB::statement('ALTER TABLE catalog_stock_locations DROP CONSTRAINT IF EXISTS catalog_stock_locations_held_for_check');
        DB::statement('ALTER TABLE catalog_stock_locations DROP CONSTRAINT IF EXISTS catalog_stock_locations_kind_check');

        Schema::table('catalog_stock_locations', function (Blueprint $table): void {
            $table->dropForeign(['organization_id', 'held_for_location_id']);
            $table->dropColumn(['kind', 'held_for_location_id']);
        });
    }
};
