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
        Schema::create('catalog_bundle_stock_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('bundle_id');
            $table->string('type', 20);
            $table->string('status', 20);
            $table->bigInteger('quantity');
            $table->uuid('location_id');
            $table->jsonb('payload');
            $table->jsonb('composition_snapshot');
            $table->uuid('out_transaction_id')->nullable();
            $table->uuid('in_transaction_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['organization_id', 'bundle_id'], 'catalog_bundle_stock_operations_bundle_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_bundles')
                ->restrictOnDelete();
            $table->foreign(['organization_id', 'location_id'], 'catalog_bundle_stock_operations_location_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_stock_locations')
                ->restrictOnDelete();
        });

        DB::statement('CREATE INDEX catalog_bundle_stock_operations_history_index ON catalog_bundle_stock_operations (organization_id, bundle_id, status, created_at) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_bundle_stock_operations_out_transaction_index ON catalog_bundle_stock_operations (organization_id, out_transaction_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_bundle_stock_operations_in_transaction_index ON catalog_bundle_stock_operations (organization_id, in_transaction_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_bundle_stock_operations_bundle_id_index ON catalog_bundle_stock_operations (bundle_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_bundle_stock_operations_location_id_index ON catalog_bundle_stock_operations (location_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_bundle_stock_operations_deleted_at_index ON catalog_bundle_stock_operations (deleted_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_bundle_stock_operations');
    }
};
