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
        Schema::create('catalog_stock_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('source_location_id');
            $table->uuid('destination_location_id');
            $table->string('status', 20);
            $table->uuid('inventory_transaction_id')->nullable();
            $table->uuid('reversal_transaction_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'id'], 'catalog_stock_transfers_org_id_unique');
            $table->foreign(['organization_id', 'source_location_id'], 'catalog_stock_transfers_source_location_foreign')
                ->references(['organization_id', 'id'])->on('catalog_stock_locations')->restrictOnDelete();
            $table->foreign(['organization_id', 'destination_location_id'], 'catalog_stock_transfers_destination_location_foreign')
                ->references(['organization_id', 'id'])->on('catalog_stock_locations')->restrictOnDelete();
            $table->foreign(['organization_id', 'inventory_transaction_id'], 'catalog_stock_transfers_inventory_transaction_foreign')
                ->references(['organization_id', 'id'])->on('inventory_transactions')->restrictOnDelete();
            $table->foreign(['organization_id', 'reversal_transaction_id'], 'catalog_stock_transfers_reversal_transaction_foreign')
                ->references(['organization_id', 'id'])->on('inventory_transactions')->restrictOnDelete();
        });

        DB::statement('CREATE INDEX catalog_stock_transfers_history_index ON catalog_stock_transfers (organization_id, status, created_at) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_stock_transfers_source_location_id_index ON catalog_stock_transfers (source_location_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_stock_transfers_destination_location_id_index ON catalog_stock_transfers (destination_location_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_stock_transfers_inventory_transaction_id_index ON catalog_stock_transfers (inventory_transaction_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_stock_transfers_reversal_transaction_id_index ON catalog_stock_transfers (reversal_transaction_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_stock_transfers');
    }
};
