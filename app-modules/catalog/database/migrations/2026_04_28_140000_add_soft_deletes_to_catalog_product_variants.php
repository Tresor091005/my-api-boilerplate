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
        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS catalog_product_variants_deleted_at_index ON catalog_product_variants (deleted_at)');
        DB::statement('DROP INDEX IF EXISTS catalog_product_variants_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_product_variants_product_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_product_variants_unit_group_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_product_variants_organization_id_index ON catalog_product_variants (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_product_variants_product_id_index ON catalog_product_variants (product_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_product_variants_unit_group_id_index ON catalog_product_variants (unit_group_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS catalog_product_variants_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_product_variants_product_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_product_variants_unit_group_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_product_variants_organization_id_index ON catalog_product_variants (organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_product_variants_product_id_index ON catalog_product_variants (product_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_product_variants_unit_group_id_index ON catalog_product_variants (unit_group_id)');
        DB::statement('DROP INDEX IF EXISTS catalog_product_variants_deleted_at_index');

        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
