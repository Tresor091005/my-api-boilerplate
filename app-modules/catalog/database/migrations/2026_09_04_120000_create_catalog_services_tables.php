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
        Schema::create('catalog_services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->text('handle')->index();
            $table->text('name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'id'], 'catalog_services_organization_id_id_unique');
            $table->unique(['organization_id', 'handle'], 'catalog_services_organization_id_handle_unique');
            $table->foreign(['organization_id', 'id'], 'catalog_services_catalog_item_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_items')
                ->cascadeOnDelete();
        });

        DB::statement('CREATE INDEX catalog_services_organization_id_index ON catalog_services (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX catalog_services_deleted_at_index ON catalog_services (deleted_at)');

        Schema::create('catalog_service_deliverable_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->uuid('service_id');
            $table->text('name');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['organization_id', 'service_id', 'position'], 'catalog_service_templates_position_unique');
            $table->unique(['organization_id', 'id'], 'catalog_service_templates_organization_id_id_unique');
            $table->foreign(['organization_id', 'service_id'], 'catalog_service_templates_service_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_services')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_service_deliverable_templates');
        Schema::dropIfExists('catalog_services');
    }
};
