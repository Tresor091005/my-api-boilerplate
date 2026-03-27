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
        Schema::create('inventory_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('external_type');
            $table->uuid('external_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX inventory_locations_external_id_external_type_unique ON inventory_locations (external_id, external_type) WHERE deleted_at IS NULL');

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('itemable_type');
            $table->uuid('itemable_id');
            $table->string('base_unit_code');
            $table->boolean('is_active')->default(true);
            $table->string('sku')->nullable()->unique();
            $table->string('deduction_strategy')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('base_unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });

        DB::statement('CREATE UNIQUE INDEX inventory_items_itemable_id_itemable_type_unique ON inventory_items (itemable_id, itemable_type) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX inventory_items_base_unit_code_index ON inventory_items (base_unit_code) WHERE deleted_at IS NULL');

        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('item_id');
            $table->uuid('location_id');
            $table->bigInteger('unit_cost');
            $table->bigInteger('quantity');
            $table->bigInteger('remaining');
            $table->string('unit_code');
            $table->string('currency_code', 3)->nullable();
            $table->timestamp('expiration_date')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('location_id')->references('id')->on('inventory_locations')->onDelete('restrict');
            $table->foreign('currency_code')->references('code')->on('master_currencies')->onDelete('restrict');
            $table->foreign('unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });

        DB::statement('ALTER TABLE inventory_stocks ADD CONSTRAINT inventory_stocks_remaining_check_non_negative CHECK (remaining >= 0)');
        DB::statement('CREATE INDEX inventory_stocks_item_id_index ON inventory_stocks (item_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX inventory_stocks_location_id_index ON inventory_stocks (location_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX inventory_stocks_currency_code_index ON inventory_stocks (currency_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX inventory_stocks_unit_code_index ON inventory_stocks (unit_code) WHERE deleted_at IS NULL');

        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference_type');
            $table->uuid('reference_id');
            $table->enum('transaction_type', ['in', 'out', 'adjustment', 'transfer']);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'inventory_transactions_reference_id_reference_type_index');
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->enum('movement_type', ['in', 'out']);
            $table->uuid('transaction_id')->index();
            $table->uuid('item_id')->index();
            $table->uuid('location_id')->index();
            $table->uuid('stock_id')->index();
            $table->bigInteger('quantity');
            $table->string('unit_code')->index();
            $table->bigInteger('unit_cost');
            $table->string('currency_code', 3)->nullable()->index();
            $table->timestamp('expiration_date')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('inventory_transactions')->onDelete('restrict');
            $table->foreign('item_id')->references('id')->on('inventory_items')->onDelete('restrict');
            $table->foreign('location_id')->references('id')->on('inventory_locations')->onDelete('restrict');
            $table->foreign('stock_id')->references('id')->on('inventory_stocks')->onDelete('no action');
            $table->foreign('unit_code')->references('code')->on('master_units')->onDelete('restrict');
            $table->foreign('currency_code')->references('code')->on('master_currencies')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_locations');
    }
};
