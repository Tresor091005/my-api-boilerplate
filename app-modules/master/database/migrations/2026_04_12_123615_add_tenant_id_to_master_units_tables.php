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
        // 1. Drop dependent foreign keys
        Schema::table('catalog_bundle_items', function (Blueprint $table): void {
            $table->dropForeign(['display_unit_code']);
        });
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropForeign(['base_unit_code']);
        });
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropForeign(['base_unit_code']);
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign(['base_unit_code']);
        });

        // 2. Update master_unit_groups
        Schema::table('master_unit_groups', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable()->after('id');
        });

        DB::statement('CREATE INDEX master_unit_groups_organization_id_index ON master_unit_groups (organization_id) WHERE deleted_at IS NULL');

        DB::statement('DROP INDEX IF EXISTS master_unit_groups_name_unique');
        DB::statement('CREATE UNIQUE INDEX master_unit_groups_name_unique ON master_unit_groups (name) WHERE organization_id IS NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX master_unit_groups_name_organization_id_unique ON master_unit_groups (name, organization_id) WHERE organization_id IS NOT NULL AND deleted_at IS NULL');

        // 3. Update master_units
        Schema::table('master_units', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable()->after('id');
            $table->dropUnique('master_units_code_unique');
        });

        DB::statement('CREATE INDEX master_units_organization_id_index ON master_units (organization_id) WHERE deleted_at IS NULL');

        // Re-create code unique constraint
        Schema::table('master_units', function (Blueprint $table): void {
            $table->unique('code', 'master_units_code_unique');
        });

        DB::statement('DROP INDEX IF EXISTS master_units_group_id_ratio_unique');
        DB::statement('CREATE UNIQUE INDEX master_units_group_id_ratio_unique ON master_units (group_id, ratio) WHERE organization_id IS NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX master_units_group_id_ratio_organization_id_unique ON master_units (group_id, ratio, organization_id) WHERE organization_id IS NOT NULL AND deleted_at IS NULL');

        // 4. Recreate foreign keys
        Schema::table('catalog_bundle_items', function (Blueprint $table): void {
            $table->foreign('display_unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->foreign('base_unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->foreign('base_unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreign('base_unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        // 1. Drop foreign keys
        Schema::table('catalog_bundle_items', function (Blueprint $table): void {
            $table->dropForeign(['display_unit_code']);
        });
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropForeign(['base_unit_code']);
        });
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropForeign(['base_unit_code']);
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign(['base_unit_code']);
        });

        // 2. Revert master_units
        Schema::table('master_units', function (Blueprint $table): void {
            DB::statement('DROP INDEX IF EXISTS master_units_group_id_ratio_organization_id_unique');
            DB::statement('DROP INDEX IF EXISTS master_units_group_id_ratio_unique');
            DB::statement('CREATE UNIQUE INDEX master_units_group_id_ratio_unique ON master_units (group_id, ratio) WHERE deleted_at IS NULL');

            DB::statement('DROP INDEX IF EXISTS master_units_organization_id_index');

            $table->dropUnique('master_units_code_unique');
            $table->unique('code', 'master_units_code_unique');

            $table->dropColumn('organization_id');
        });

        // 3. Revert master_unit_groups
        Schema::table('master_unit_groups', function (Blueprint $table): void {
            DB::statement('DROP INDEX IF EXISTS master_unit_groups_name_organization_id_unique');
            DB::statement('DROP INDEX IF EXISTS master_unit_groups_name_unique');
            DB::statement('CREATE UNIQUE INDEX master_unit_groups_name_unique ON master_unit_groups (name) WHERE deleted_at IS NULL');

            DB::statement('DROP INDEX IF EXISTS master_unit_groups_organization_id_index');

            $table->dropColumn('organization_id');
        });

        // 4. Recreate foreign keys
        Schema::table('catalog_bundle_items', function (Blueprint $table): void {
            $table->foreign('display_unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->foreign('base_unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->foreign('base_unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreign('base_unit_code')->references('code')->on('master_units')->onDelete('restrict');
        });
    }
};
