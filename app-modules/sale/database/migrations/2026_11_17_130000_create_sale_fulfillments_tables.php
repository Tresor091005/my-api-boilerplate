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
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->unique(['organization_id', 'id'], 'sale_lines_organization_id_id_unique');
        });

        Schema::create('sale_fulfillments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('sale_id');
            $table->string('number', 50);
            $table->string('status', 20)->default('draft');
            $table->uuid('inventory_transaction_id')->nullable();
            $table->uuid('reversal_transaction_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id'], 'sale_fulfillments_organization_id_id_unique');
            $table->unique(['organization_id', 'number'], 'sale_fulfillments_organization_number_unique');
            $table->index(['organization_id', 'sale_id', 'created_at'], 'sale_fulfillments_sale_created_at_index');
            $table->index(['organization_id', 'inventory_transaction_id'], 'sale_fulfillments_inventory_transaction_index');
            $table->index(['organization_id', 'reversal_transaction_id'], 'sale_fulfillments_reversal_transaction_index');
            $table->foreign(['organization_id', 'sale_id'], 'sale_fulfillments_sale_foreign')
                ->references(['organization_id', 'id'])->on('sale_sales')->restrictOnDelete();
            $table->foreign(['organization_id', 'inventory_transaction_id'], 'sale_fulfillments_inventory_transaction_foreign')
                ->references(['organization_id', 'id'])->on('inventory_transactions')->restrictOnDelete();
            $table->foreign(['organization_id', 'reversal_transaction_id'], 'sale_fulfillments_reversal_transaction_foreign')
                ->references(['organization_id', 'id'])->on('inventory_transactions')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE sale_fulfillments ADD CONSTRAINT sale_fulfillments_status_check CHECK (status IN ('draft', 'completed', 'cancelled'))");

        Schema::create('sale_fulfillment_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('fulfillment_id');
            $table->uuid('sale_line_id');
            // the warehouse the goods leave from; empty for an item whose stock is not tracked
            $table->uuid('stock_location_id')->nullable();
            // in the ratio-1 base unit, like the sale line
            $table->bigInteger('quantity');
            $table->jsonb('stock_ids')->nullable();
            // what the goods cost when they left, in minor units of the functional currency
            $table->bigInteger('total_cost')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'fulfillment_id'], 'sale_fulfillment_lines_fulfillment_index');
            $table->index(['organization_id', 'sale_line_id'], 'sale_fulfillment_lines_sale_line_index');
            $table->index(['organization_id', 'stock_location_id'], 'sale_fulfillment_lines_stock_location_index');
            $table->foreign(['organization_id', 'fulfillment_id'], 'sale_fulfillment_lines_fulfillment_foreign')
                ->references(['organization_id', 'id'])->on('sale_fulfillments')->cascadeOnDelete();
            $table->foreign(['organization_id', 'sale_line_id'], 'sale_fulfillment_lines_sale_line_foreign')
                ->references(['organization_id', 'id'])->on('sale_lines')->restrictOnDelete();
            $table->foreign(['organization_id', 'stock_location_id'], 'sale_fulfillment_lines_stock_location_foreign')
                ->references(['organization_id', 'id'])->on('catalog_stock_locations')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE sale_fulfillment_lines ADD CONSTRAINT sale_fulfillment_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('CREATE UNIQUE INDEX sale_fulfillment_lines_line_location_unique ON sale_fulfillment_lines (organization_id, fulfillment_id, sale_line_id, stock_location_id) NULLS NOT DISTINCT');

        Schema::create('sale_holds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('sale_id');
            $table->uuid('sale_line_id');
            // the warehouse the goods were set aside in, in its customer hold location
            $table->uuid('stock_location_id');
            // in the ratio-1 base unit, set aside at confirmation
            $table->bigInteger('quantity');
            // given back to the warehouse when the sale was cancelled
            $table->bigInteger('released_quantity')->default(0);
            $table->uuid('inventory_transaction_id');
            $table->uuid('release_transaction_id')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'sale_line_id', 'stock_location_id'], 'sale_holds_line_location_unique');
            $table->index(['organization_id', 'sale_id'], 'sale_holds_sale_index');
            $table->index(['organization_id', 'stock_location_id'], 'sale_holds_stock_location_index');
            $table->index(['organization_id', 'inventory_transaction_id'], 'sale_holds_inventory_transaction_index');
            $table->index(['organization_id', 'release_transaction_id'], 'sale_holds_release_transaction_index');
            $table->foreign(['organization_id', 'sale_id'], 'sale_holds_sale_foreign')
                ->references(['organization_id', 'id'])->on('sale_sales')->restrictOnDelete();
            $table->foreign(['organization_id', 'sale_line_id'], 'sale_holds_sale_line_foreign')
                ->references(['organization_id', 'id'])->on('sale_lines')->restrictOnDelete();
            $table->foreign(['organization_id', 'stock_location_id'], 'sale_holds_stock_location_foreign')
                ->references(['organization_id', 'id'])->on('catalog_stock_locations')->restrictOnDelete();
            $table->foreign(['organization_id', 'inventory_transaction_id'], 'sale_holds_inventory_transaction_foreign')
                ->references(['organization_id', 'id'])->on('inventory_transactions')->restrictOnDelete();
            $table->foreign(['organization_id', 'release_transaction_id'], 'sale_holds_release_transaction_foreign')
                ->references(['organization_id', 'id'])->on('inventory_transactions')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE sale_holds ADD CONSTRAINT sale_holds_quantity_check CHECK (quantity > 0 AND released_quantity BETWEEN 0 AND quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_holds');
        Schema::dropIfExists('sale_fulfillment_lines');
        Schema::dropIfExists('sale_fulfillments');

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropUnique('sale_lines_organization_id_id_unique');
        });
    }
};
