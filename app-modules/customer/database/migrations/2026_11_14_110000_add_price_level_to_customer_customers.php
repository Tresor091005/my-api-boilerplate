<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The only price level this customer is sold at; null when the sale chooses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_customers', function (Blueprint $table): void {
            $table->uuid('price_level_id')->nullable();

            $table->foreign(['organization_id', 'price_level_id'], 'customer_customers_price_level_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_price_levels')
                ->restrictOnDelete();
        });

        DB::statement('CREATE INDEX customer_customers_price_level_index ON customer_customers (organization_id, price_level_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customer_customers_price_level_index');

        Schema::table('customer_customers', function (Blueprint $table): void {
            $table->dropForeign('customer_customers_price_level_foreign');
            $table->dropColumn('price_level_id');
        });
    }
};
