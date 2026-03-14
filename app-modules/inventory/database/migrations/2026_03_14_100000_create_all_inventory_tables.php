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
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX inventory_locations_external_unique ON inventory_locations (external_type, external_id) WHERE deleted_at IS NULL');

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('itemable_type');
            $table->uuid('itemable_id');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX inventory_items_itemable_unique ON inventory_items (itemable_type, itemable_id) WHERE deleted_at IS NULL');

        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('item_id')
                ->index()
                ->constrained('inventory_items')
                ->onDelete('cascade');

            $table->foreignUuid('location_id')
                ->index()
                ->constrained('inventory_locations')
                ->onDelete('cascade');

            $table->bigInteger('unit_cost');
            $table->string('currency_code', 3)->index();
            $table->foreign('currency_code')
                ->references('code')
                ->on('catalog_currencies')
                ->onDelete('restrict');

            $table->bigInteger('quantity');
            $table->bigInteger('remaining');
            $table->foreignUuid('unit_id')
                ->index()
                ->constrained('catalog_units')
                ->onDelete('restrict');

            $table->timestamp('peremption_date')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('reference');
            $table->enum('transaction_type', ['in', 'out', 'adjustment', 'transfer']);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->enum('movement_type', ['in', 'out']);
            $table->foreignUuid('transaction_id')
                ->index()
                ->constrained('inventory_transactions')
                ->onDelete('restrict');

            $table->foreignUuid('item_id')
                ->index()
                ->constrained('inventory_items')
                ->onDelete('restrict');

            $table->foreignUuid('location_id')
                ->index()
                ->constrained('inventory_locations')
                ->onDelete('restrict');

            $table->foreignUuid('stock_id')
                ->index()
                ->constrained('inventory_stocks')
                ->onDelete('restrict');

            $table->bigInteger('quantity');
            $table->foreignUuid('unit_id')
                ->index()
                ->constrained('catalog_units')
                ->onDelete('restrict');

            $table->bigInteger('unit_cost');
            $table->string('currency_code', 3)->index();
            $table->foreign('currency_code')
                ->references('code')
                ->on('catalog_currencies')
                ->onDelete('restrict');

            $table->timestamp('peremption_date')->nullable();

            $table->timestamps();
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
