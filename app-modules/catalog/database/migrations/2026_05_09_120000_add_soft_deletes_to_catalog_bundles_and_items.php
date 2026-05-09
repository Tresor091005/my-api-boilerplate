<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_bundles', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS catalog_bundles_deleted_at_index ON catalog_bundles (deleted_at)');
        DB::statement('DROP INDEX IF EXISTS catalog_bundles_organization_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_bundles_organization_id_index ON catalog_bundles (organization_id) WHERE deleted_at IS NULL');
        DB::statement('DROP INDEX IF EXISTS catalog_bundles_unit_code_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_bundles_unit_code_index ON catalog_bundles (unit_code) WHERE deleted_at IS NULL');
        DB::statement('ALTER TABLE catalog_bundles DROP CONSTRAINT IF EXISTS catalog_bundles_organization_id_handle_unique');
        DB::statement('DROP INDEX IF EXISTS catalog_bundles_organization_id_handle_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS catalog_bundles_organization_id_handle_unique ON catalog_bundles (organization_id, handle) WHERE deleted_at IS NULL');

        Schema::table('catalog_bundle_items', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS catalog_bundle_items_deleted_at_index ON catalog_bundle_items (deleted_at)');
        DB::statement('DROP INDEX IF EXISTS catalog_bundle_items_item_type_item_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_bundle_items_item_type_item_id_index ON catalog_bundle_items (item_type, item_id) WHERE deleted_at IS NULL');
        DB::statement('DROP INDEX IF EXISTS catalog_bundle_items_organization_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_bundle_items_organization_id_index ON catalog_bundle_items (organization_id) WHERE deleted_at IS NULL');
        DB::statement('DROP INDEX IF EXISTS catalog_bundle_items_bundle_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_bundle_items_bundle_id_index ON catalog_bundle_items (bundle_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS catalog_bundle_items_item_type_item_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_bundle_items_item_type_item_id_index ON catalog_bundle_items (item_type, item_id)');
        DB::statement('DROP INDEX IF EXISTS catalog_bundle_items_bundle_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_bundle_items_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_bundle_items_deleted_at_index');

        Schema::table('catalog_bundle_items', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        DB::statement('DROP INDEX IF EXISTS catalog_bundles_organization_id_handle_unique');
        DB::statement('ALTER TABLE catalog_bundles ADD CONSTRAINT catalog_bundles_organization_id_handle_unique UNIQUE (organization_id, handle)');
        DB::statement('DROP INDEX IF EXISTS catalog_bundles_unit_code_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_bundles_unit_code_index ON catalog_bundles (unit_code)');
        DB::statement('DROP INDEX IF EXISTS catalog_bundles_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_bundles_deleted_at_index');

        Schema::table('catalog_bundles', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
