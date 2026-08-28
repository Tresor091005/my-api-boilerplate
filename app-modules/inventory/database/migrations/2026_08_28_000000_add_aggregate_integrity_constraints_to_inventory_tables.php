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
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->unique(['organization_id', 'id'], 'inventory_items_organization_id_id_unique');
        });

        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->unique(['organization_id', 'id'], 'inventory_locations_organization_id_id_unique');
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->unique(['organization_id', 'id'], 'inventory_transactions_organization_id_id_unique');
        });

        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'id'],
                'inventory_stocks_organization_id_id_unique',
            );
            $table->unique(
                ['organization_id', 'id', 'item_id', 'location_id'],
                'inventory_stocks_aggregate_identity_unique',
            );
            $table->foreign(['organization_id', 'item_id'], 'inventory_stocks_organization_item_fk')
                ->references(['organization_id', 'id'])
                ->on('inventory_items')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'location_id'], 'inventory_stocks_organization_location_fk')
                ->references(['organization_id', 'id'])
                ->on('inventory_locations')
                ->restrictOnDelete();
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->foreign(
                ['organization_id', 'reversal_of_transaction_id'],
                'inventory_transactions_organization_reversal_fk',
            )
                ->references(['organization_id', 'id'])
                ->on('inventory_transactions')
                ->restrictOnDelete();
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreign(['organization_id', 'transaction_id'], 'inventory_movements_organization_transaction_fk')
                ->references(['organization_id', 'id'])
                ->on('inventory_transactions')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'item_id'], 'inventory_movements_organization_item_fk')
                ->references(['organization_id', 'id'])
                ->on('inventory_items')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'location_id'], 'inventory_movements_organization_location_fk')
                ->references(['organization_id', 'id'])
                ->on('inventory_locations')
                ->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'stock_id', 'item_id', 'location_id'],
                'inventory_movements_stock_aggregate_fk',
            )
                ->references(['organization_id', 'id', 'item_id', 'location_id'])
                ->on('inventory_stocks')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign('inventory_movements_organization_transaction_fk');
            $table->dropForeign('inventory_movements_organization_item_fk');
            $table->dropForeign('inventory_movements_organization_location_fk');
            $table->dropForeign('inventory_movements_stock_aggregate_fk');
        });

        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropForeign('inventory_stocks_organization_item_fk');
            $table->dropForeign('inventory_stocks_organization_location_fk');
            $table->dropUnique('inventory_stocks_aggregate_identity_unique');
            $table->dropUnique('inventory_stocks_organization_id_id_unique');
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropForeign('inventory_transactions_organization_reversal_fk');
            $table->dropUnique('inventory_transactions_organization_id_id_unique');
        });

        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->dropUnique('inventory_locations_organization_id_id_unique');
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropUnique('inventory_items_organization_id_id_unique');
        });
    }
};
