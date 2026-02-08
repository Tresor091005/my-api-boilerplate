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
        // Independent Tables
        Schema::create('catalog_currencies', function (Blueprint $table) {
            $table->string('code', 3)->primary();
            $table->text('name');
            $table->string('symbol', 10);
            $table->integer('precision')->default(2);
            $table->timestamps();
        });

        Schema::create('catalog_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique()->index();
            $table->text('name');
            $table->integer('base_ratio')->default(10000);
            $table->boolean('is_base')->default(false);
            $table->timestamps();
        });

        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('handle')->unique()->index();
            $table->text('name');
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::table('catalog_categories', function (Blueprint $table) {
            $table->foreignUuid('parent_id')
                ->nullable()
                ->index()
                ->constrained('catalog_categories')
                ->onDelete('restrict');
        });

        Schema::create('catalog_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('handle')->unique()->index();
            $table->text('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('catalog_product_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('handle')->unique()->index();
            $table->text('name');
            $table->timestamps();
        });

        Schema::create('catalog_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('handle')->unique()->index();
            $table->text('name');
            $table->timestamps();
        });

        Schema::create('catalog_product_option_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('option_id')
                ->index()
                ->constrained('catalog_product_options')
                ->onDelete('cascade');
            $table->text('handle')->index();
            $table->text('value');
            $table->timestamps();
            $table->unique(['option_id', 'handle']);
        });

        Schema::create('catalog_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('priceable');
            $table->string('currency_code', 3)->index();
            $table->foreign('currency_code')
                ->references('code')
                ->on('catalog_currencies')
                ->onDelete('restrict');
            $table->integer('min_quantity')->default(1);
            $table->integer('max_quantity')->nullable();
            $table->integer('step')->default(1);
            $table->bigInteger('amount');
            $table->boolean('is_active')->default(true);
            $table->timestamp('active_from')->nullable()->index();
            $table->timestamp('active_to')->nullable()->index();
            $table->timestamps();

            $table->unique(['priceable_type', 'priceable_id', 'currency_code', 'min_quantity', 'step'], 'catalog_prices_unique_idx');
        });

        Schema::create('catalog_bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('handle')->unique()->index();
            $table->text('name');
            $table->foreignUuid('unit_id')
                ->nullable()
                ->index()
                ->constrained('catalog_units')
                ->onDelete('restrict');
            $table->integer('step')->default(1);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('catalog_bundle_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('item');
            $table->foreignUuid('bundle_id')
                ->index()
                ->constrained('catalog_bundles')
                ->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('catalog_product_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')
                ->index()
                ->constrained('catalog_products')
                ->onDelete('cascade');
            $table->foreignUuid('category_id')
                ->index()
                ->constrained('catalog_categories')
                ->onDelete('cascade');
            $table->unique(['product_id', 'category_id']);
        });

        Schema::create('catalog_product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')
                ->index()
                ->constrained('catalog_products')
                ->onDelete('cascade');
            $table->text('handle')->index();
            $table->text('sku')->unique()->index();
            $table->foreignUuid('unit_id')
                ->nullable()
                ->index()
                ->constrained('catalog_units')
                ->onDelete('restrict');
            $table->integer('min_quantity')->default(1);
            $table->integer('max_quantity')->nullable();
            $table->integer('step')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_stockable')->default(true);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('catalog_product_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')
                ->index()
                ->constrained('catalog_products')
                ->onDelete('cascade');
            $table->foreignUuid('tag_id')
                ->index()
                ->constrained('catalog_tags')
                ->onDelete('cascade');
            $table->unique(['product_id', 'tag_id']);
        });

        Schema::create('catalog_product_variant_option_value', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('variant_id')
                ->index()
                ->constrained('catalog_product_variants')
                ->onDelete('cascade');
            $table->foreignUuid('option_value_id')
                ->index()
                ->constrained('catalog_product_option_values')
                ->onDelete('cascade');
            $table->unique(['variant_id', 'option_value_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_product_variant_option_value');
        Schema::dropIfExists('catalog_product_tags');
        Schema::dropIfExists('catalog_product_variants');
        Schema::dropIfExists('catalog_product_categories');
        Schema::dropIfExists('catalog_bundle_items');
        Schema::dropIfExists('catalog_bundles');
        Schema::dropIfExists('catalog_prices');
        Schema::dropIfExists('catalog_product_option_values');
        Schema::dropIfExists('catalog_tags');
        Schema::dropIfExists('catalog_product_options');
        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('catalog_categories');
        Schema::dropIfExists('catalog_units');
        Schema::dropIfExists('catalog_currencies');
    }
};
