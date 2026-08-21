<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->index()
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->text('handle')->index();
            $table->text('name');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'handle'], 'catalog_categories_organization_id_handle_unique');
        });

        Schema::table('catalog_categories', function (Blueprint $table): void {
            $table->foreignUuid('parent_id')
                ->nullable()
                ->index()
                ->constrained('catalog_categories')
                ->onDelete('restrict');
        });

        Schema::create('catalog_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->index()
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->text('handle')->index();
            $table->text('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'handle'], 'catalog_products_organization_id_handle_unique');
        });

        Schema::create('catalog_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->index()
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->text('name');
            $table->timestamps();

            $table->unique(['organization_id', 'name'], 'catalog_options_organization_id_name_unique');
        });

        Schema::create('catalog_option_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->index()
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->foreignUuid('option_id')
                ->index()
                ->constrained('catalog_options')
                ->onDelete('cascade');
            $table->text('value');
            $table->timestamps();

            $table->unique(['organization_id', 'option_id', 'value'], 'catalog_option_values_organization_id_option_id_value_unique');
        });

        Schema::create('catalog_bundles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->index()
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->text('handle')->index();
            $table->text('name');
            $table->string('unit_code')
                ->nullable()
                ->index();
            $table->foreign('unit_code')
                ->references('code')
                ->on('master_units')
                ->onDelete('restrict');
            $table->integer('step')->default(1);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'handle'], 'catalog_bundles_organization_id_handle_unique');
        });

        Schema::create('catalog_bundle_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->index()
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->uuidMorphs('item', 'catalog_bundle_items_item_type_item_id_index');
            $table->foreignUuid('bundle_id')
                ->index()
                ->constrained('catalog_bundles')
                ->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('catalog_product_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')
                ->index()
                ->constrained('catalog_products')
                ->onDelete('cascade');
            $table->foreignUuid('category_id')
                ->index()
                ->constrained('catalog_categories')
                ->onDelete('cascade');
            $table->unique(['category_id', 'product_id']);
        });

        Schema::create('catalog_product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->index()
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->foreignUuid('product_id')
                ->index()
                ->constrained('catalog_products')
                ->onDelete('cascade');
            $table->text('sku')->index();
            $table->string('unit_code')
                ->nullable()
                ->index();
            $table->foreign('unit_code')
                ->references('code')
                ->on('master_units')
                ->onDelete('restrict');
            $table->integer('min_quantity')->default(1);
            $table->integer('max_quantity')->nullable();
            $table->integer('step')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_stockable')->default(true);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'sku'], 'catalog_product_variants_organization_id_sku_unique');
        });

        Schema::create('catalog_variant_option_value', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')
                ->index()
                ->constrained('catalog_products')
                ->onDelete('cascade');
            $table->foreignUuid('variant_id')
                ->index()
                ->constrained('catalog_product_variants')
                ->onDelete('cascade');
            $table->foreignUuid('option_value_id')
                ->index()
                ->constrained('catalog_option_values')
                ->onDelete('cascade');
            $table->foreignUuid('option_id')
                ->index()
                ->constrained('catalog_options')
                ->onDelete('cascade');
            $table->unique(['option_id', 'variant_id']);
        });

        // TODO universal labels system
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_variant_option_value');
        Schema::dropIfExists('catalog_product_variants');
        Schema::dropIfExists('catalog_product_categories');
        Schema::dropIfExists('catalog_bundle_items');
        Schema::dropIfExists('catalog_bundles');
        Schema::dropIfExists('catalog_option_values');
        Schema::dropIfExists('catalog_options');
        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('catalog_categories');
    }
};
