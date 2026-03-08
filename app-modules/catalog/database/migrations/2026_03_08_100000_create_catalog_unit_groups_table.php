<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create the unit groups table
        Schema::create('catalog_unit_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Add partial unique index for catalog_unit_groups
        DB::statement('CREATE UNIQUE INDEX catalog_unit_groups_name_unique ON catalog_unit_groups (name) WHERE deleted_at IS NULL');

        // 2. Get unique groups and insert them
        $groups = DB::table('catalog_units')
            ->select('unit_group', 'is_builtin')
            ->distinct()
            ->get();

        $now = now();
        foreach ($groups as $group) {
            DB::table('catalog_unit_groups')->insert([
                'id'         => (string) Str::uuid7(),
                'name'       => $group->unit_group,
                'is_builtin' => $group->is_builtin,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 3. Add group_id and softDeletes to units table, and drop is_active & is_builtin
        Schema::table('catalog_units', function (Blueprint $table): void {
            $table->uuid('group_id')->after('ratio')->nullable();
            $table->softDeletes()->after('updated_at');
            $table->dropColumn(['is_active', 'is_builtin']);
        });

        // 4. Link units to their new groups
        $unitGroups = DB::table('catalog_unit_groups')->get();
        foreach ($unitGroups as $group) {
            DB::table('catalog_units')
                ->where('unit_group', $group->name)
                ->update(['group_id' => $group->id]);
        }

        // 5. Cleanup units table: remove old column and add constraints
        Schema::table('catalog_units', function (Blueprint $table): void {
            // Drop old unique constraint (catalog_units_unit_group_ratio_unique)
            $table->dropUnique(['unit_group', 'ratio']);
            $table->dropColumn('unit_group');

            // Make FK mandatory and add constraint
            $table->uuid('group_id')->nullable(false)->change();
            $table->foreign('group_id')
                ->references('id')
                ->on('catalog_unit_groups')
                ->onDelete('restrict');
        });

        // Add partial unique index for catalog_units
        DB::statement('CREATE UNIQUE INDEX catalog_units_group_id_ratio_unique ON catalog_units (group_id, ratio) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_units', function (Blueprint $table): void {
            $table->text('unit_group')->after('ratio')->nullable();
            $table->boolean('is_active')->default(true)->after('unit_group');
            $table->boolean('is_builtin')->default(false)->after('is_active');
        });

        // Restore unit_group and is_builtin data
        $unitGroups = DB::table('catalog_unit_groups')->get();
        foreach ($unitGroups as $group) {
            DB::table('catalog_units')
                ->where('group_id', $group->id)
                ->update([
                    'unit_group' => $group->name,
                    'is_builtin' => $group->is_builtin,
                ]);
        }

        Schema::table('catalog_units', function (Blueprint $table): void {
            // Drop partial unique index
            DB::statement('DROP INDEX IF EXISTS catalog_units_group_id_ratio_unique');

            $table->dropForeign(['group_id']);
            $table->dropColumn(['group_id', 'deleted_at']);

            $table->text('unit_group')->nullable(false)->change();
            $table->unique(['unit_group', 'ratio']);
        });

        Schema::dropIfExists('catalog_unit_groups');
    }
};
