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
        Schema::create('catalog_stock_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->string('handle', 100);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX catalog_stock_locations_organization_handle_unique ON catalog_stock_locations (organization_id, handle)');
        DB::statement('CREATE INDEX catalog_stock_locations_organization_id_index ON catalog_stock_locations (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_stock_locations_deleted_at_index ON catalog_stock_locations (deleted_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_stock_locations');
    }
};
