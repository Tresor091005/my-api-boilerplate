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
        Schema::create('catalog_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->string('item_type', 50);
            $table->text('sku');
            $table->foreignUuid('unit_group_id')
                ->constrained('master_unit_groups')
                ->restrictOnDelete();
            $table->boolean('is_stockable');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'sku'], 'catalog_items_organization_id_sku_unique');
            $table->unique(['organization_id', 'id'], 'catalog_items_organization_id_id_unique');
        });

        DB::statement('CREATE INDEX catalog_items_organization_id_index ON catalog_items (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_items_organization_id_item_type_index ON catalog_items (organization_id, item_type) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_items_unit_group_id_index ON catalog_items (unit_group_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_items_deleted_at_index ON catalog_items (deleted_at)');

        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->dropUnique('catalog_product_variants_organization_id_sku_unique');
            $table->dropIndex('catalog_product_variants_sku_index');
            $table->dropForeign(['unit_group_id']);
            $table->dropColumn(['sku', 'unit_group_id', 'is_active']);
            $table->foreign(['organization_id', 'id'], 'catalog_product_variants_catalog_item_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('catalog_product_variants')->exists() || DB::table('catalog_items')->exists()) {
            throw new RuntimeException(
                'Cannot reverse CatalogItem identity migration while catalog records exist.'
            );
        }

        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->dropForeign('catalog_product_variants_catalog_item_foreign');
            $table->text('sku')->index();
            $table->foreignUuid('unit_group_id')
                ->after('sku')
                ->index()
                ->constrained('master_unit_groups')
                ->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->unique(['organization_id', 'sku'], 'catalog_product_variants_organization_id_sku_unique');
        });

        Schema::dropIfExists('catalog_items');
    }
};
