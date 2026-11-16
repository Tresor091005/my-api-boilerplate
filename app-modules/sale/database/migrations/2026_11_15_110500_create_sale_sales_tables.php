<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A sale, its lines and the history of its gestures. A sale is never deleted;
 * its amounts are minor units of its currency. The lines copy what they must
 * still show when the catalog changes, among them the price level each was
 * sold at, and keep the tax calculation as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->string('number', 50);
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->uuid('customer_id');
            $table->string('customer_name', 100);
            $table->string('currency_code', 3);
            $table->string('mode', 30);
            $table->unsignedSmallInteger('required_payment_percent');
            $table->date('payment_due_date')->nullable();
            $table->date('applied_payment_due_date')->nullable();
            $table->jsonb('approval_reasons')->nullable();
            $table->bigInteger('lines_base_total')->default(0);
            $table->bigInteger('lines_tax_total')->default(0);
            $table->bigInteger('lines_total')->default(0);
            $table->bigInteger('total')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'id'], 'sale_sales_organization_id_id_unique');
            $table->unique(['organization_id', 'number'], 'sale_sales_organization_number_unique');
            $table->index(['organization_id', 'created_at'], 'sale_sales_organization_created_at_index');
            $table->index(['organization_id', 'status', 'created_at'], 'sale_sales_status_created_at_index');
            $table->index(['organization_id', 'customer_id', 'created_at'], 'sale_sales_customer_created_at_index');
            $table->index('currency_code', 'sale_sales_currency_code_index');
            $table->foreign(['organization_id', 'customer_id'], 'sale_sales_customer_foreign')
                ->references(['organization_id', 'id'])
                ->on('customer_customers')
                ->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('master_currencies')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE sale_sales ADD CONSTRAINT sale_sales_status_check CHECK (status IN ('draft', 'waiting_approval', 'rejected', 'approved', 'active', 'completed', 'cancelling', 'cancelled', 'abandoned'))");
        DB::statement("ALTER TABLE sale_sales ADD CONSTRAINT sale_sales_mode_check CHECK (mode IN ('counter', 'order_invoiced_now', 'order_invoiced_on_delivery'))");
        DB::statement('ALTER TABLE sale_sales ADD CONSTRAINT sale_sales_version_check CHECK (version >= 1)');
        DB::statement('ALTER TABLE sale_sales ADD CONSTRAINT sale_sales_required_payment_percent_check CHECK (required_payment_percent BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE sale_sales ADD CONSTRAINT sale_sales_amounts_check CHECK (lines_base_total >= 0 AND lines_tax_total >= 0 AND lines_total >= 0 AND total >= 0)');

        Schema::create('sale_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('sale_id');
            $table->unsignedSmallInteger('position');
            $table->uuid('catalog_item_id');
            // in the ratio-1 base unit of the item's unit group
            $table->bigInteger('quantity');
            // the unit the line is sold and priced in
            $table->string('display_unit_code', 100);
            $table->string('item_type', 30);
            $table->text('item_name');
            $table->text('item_sku');
            $table->text('unit_name');
            $table->uuid('price_level_id');
            $table->string('price_level_name', 100);
            $table->bigInteger('unit_amount');
            $table->uuid('tax_profile_id')->nullable();
            $table->bigInteger('amount');
            $table->bigInteger('base_amount');
            $table->bigInteger('included_tax_amount');
            $table->bigInteger('added_tax_amount');
            $table->bigInteger('total_amount');
            $table->jsonb('tax_result');
            $table->timestamps();

            $table->unique(['organization_id', 'sale_id', 'catalog_item_id', 'display_unit_code'], 'sale_lines_sale_item_unit_unique');
            $table->index(['organization_id', 'catalog_item_id'], 'sale_lines_catalog_item_index');
            $table->index(['organization_id', 'price_level_id'], 'sale_lines_price_level_index');
            $table->index('display_unit_code', 'sale_lines_display_unit_code_index');
            $table->foreign(['organization_id', 'sale_id'], 'sale_lines_sale_foreign')
                ->references(['organization_id', 'id'])
                ->on('sale_sales')
                ->cascadeOnDelete();
            $table->foreign(['organization_id', 'catalog_item_id'], 'sale_lines_catalog_item_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_items')
                ->restrictOnDelete();
            $table->foreign('display_unit_code', 'sale_lines_display_unit_code_foreign')
                ->references('code')
                ->on('master_units')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'price_level_id'], 'sale_lines_price_level_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_price_levels')
                ->restrictOnDelete();
        });

        DB::statement('ALTER TABLE sale_lines ADD CONSTRAINT sale_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sale_lines ADD CONSTRAINT sale_lines_amounts_check CHECK (unit_amount >= 0 AND amount >= 0 AND base_amount >= 0 AND included_tax_amount >= 0 AND added_tax_amount >= 0 AND total_amount >= 0)');

        Schema::create('sale_transitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('sale_id');
            $table->string('event', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignUuid('member_id')
                ->nullable()
                ->constrained('iam_organization_members')
                ->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['organization_id', 'sale_id', 'created_at'], 'sale_transitions_sale_created_at_index');
            $table->index('member_id', 'sale_transitions_member_id_index');
            $table->foreign(['organization_id', 'sale_id'], 'sale_transitions_sale_foreign')
                ->references(['organization_id', 'id'])
                ->on('sale_sales')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_transitions');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sale_sales');
    }
};
