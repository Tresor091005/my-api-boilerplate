<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A price level ("retail", "wholesale") is a named list of prices in one
 * currency; an item has at most one price per level and sale unit, in minor
 * units of the level's currency. A price lists the taxes its amount already
 * contains; without any, it is a price before tax.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_price_levels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->string('name', 100);
            $table->string('currency_code', 3);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'id'], 'catalog_price_levels_organization_id_id_unique');
            $table->foreign('currency_code')->references('code')->on('master_currencies')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX catalog_price_levels_organization_currency_name_unique ON catalog_price_levels (organization_id, currency_code, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_price_levels_currency_code_index ON catalog_price_levels (currency_code) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE catalog_price_levels ADD CONSTRAINT catalog_price_levels_name_lowercase_check CHECK (name = lower(name))');

        Schema::create('catalog_item_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('catalog_item_id');
            $table->uuid('price_level_id');
            $table->foreignUuid('unit_id')->constrained('master_units')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'id'], 'catalog_item_prices_organization_id_id_unique');
            $table->foreign(['organization_id', 'catalog_item_id'], 'catalog_item_prices_catalog_item_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_items')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'price_level_id'], 'catalog_item_prices_price_level_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_price_levels')
                ->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX catalog_item_prices_item_level_unit_unique ON catalog_item_prices (organization_id, catalog_item_id, price_level_id, unit_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_item_prices_price_level_index ON catalog_item_prices (organization_id, price_level_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_item_prices_unit_id_index ON catalog_item_prices (unit_id) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE catalog_item_prices ADD CONSTRAINT catalog_item_prices_amount_check CHECK (amount >= 0)');

        Schema::create('catalog_item_price_taxes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('catalog_item_price_id');
            $table->uuid('tax_id');

            $table->foreign(['organization_id', 'catalog_item_price_id'], 'catalog_item_price_taxes_price_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_item_prices')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'tax_id'], 'catalog_item_price_taxes_tax_foreign')
                ->references(['organization_id', 'id'])
                ->on('tax_taxes')
                ->restrictOnDelete();
            $table->unique(['organization_id', 'catalog_item_price_id', 'tax_id'], 'catalog_item_price_taxes_price_tax_unique');
            $table->index(['organization_id', 'tax_id'], 'catalog_item_price_taxes_tax_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_item_price_taxes');
        Schema::dropIfExists('catalog_item_prices');
        Schema::dropIfExists('catalog_price_levels');
    }
};
