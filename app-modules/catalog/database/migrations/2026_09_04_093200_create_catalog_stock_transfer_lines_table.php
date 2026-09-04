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
        Schema::create('catalog_stock_transfer_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('stock_transfer_id');
            $table->string('catalog_item_type', 50);
            $table->uuid('catalog_item_id');
            $table->unsignedInteger('position');
            $table->bigInteger('quantity');
            $table->string('display_unit_code', 100);
            $table->string('strategy', 20)->nullable();
            $table->jsonb('stock_ids')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['organization_id', 'stock_transfer_id'], 'catalog_stock_transfer_lines_transfer_foreign')
                ->references(['organization_id', 'id'])->on('catalog_stock_transfers')->cascadeOnDelete();
            $table->foreign(['organization_id', 'catalog_item_id', 'catalog_item_type'], 'catalog_stock_transfer_lines_item_foreign')
                ->references(['organization_id', 'id', 'item_type'])->on('catalog_items')->restrictOnDelete();
            $table->foreign('display_unit_code')->references('code')->on('master_units')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE catalog_stock_transfer_lines ADD CONSTRAINT catalog_stock_transfer_lines_quantity_positive CHECK (quantity > 0)');
        DB::statement('CREATE INDEX catalog_stock_transfer_lines_catalog_item_type_id_index ON catalog_stock_transfer_lines (catalog_item_type, catalog_item_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_stock_transfer_lines_stock_transfer_id_index ON catalog_stock_transfer_lines (stock_transfer_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_stock_transfer_lines_catalog_item_id_index ON catalog_stock_transfer_lines (catalog_item_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_stock_transfer_lines_display_unit_code_index ON catalog_stock_transfer_lines (display_unit_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX catalog_stock_transfer_lines_item_unique ON catalog_stock_transfer_lines (organization_id, stock_transfer_id, catalog_item_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_stock_transfer_lines_transfer_index ON catalog_stock_transfer_lines (organization_id, stock_transfer_id, position) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_stock_transfer_lines');
    }
};
