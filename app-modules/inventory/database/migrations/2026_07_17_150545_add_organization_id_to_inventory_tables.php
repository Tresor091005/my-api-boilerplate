<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ([
            'inventory_items',
            'inventory_locations',
            'inventory_stocks',
            'inventory_transactions',
            'inventory_movements',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignUuid('organization_id')
                    ->nullable()
                    ->index()
                    ->constrained('organization_organizations')
                    ->onDelete('restrict');
            });
        }

        foreach ([
            'inventory_items',
            'inventory_locations',
            'inventory_stocks',
            'inventory_transactions',
            'inventory_movements',
        ] as $tableName) {
            if (DB::table($tableName)->exists()) {
                throw new RuntimeException(
                    "Cannot add organization_id to {$tableName}: existing rows require an explicit organization backfill."
                );
            }

            DB::statement("ALTER TABLE {$tableName} ALTER COLUMN organization_id SET NOT NULL");
        }

        foreach ([
            'inventory_items',
            'inventory_locations',
            'inventory_stocks',
        ] as $tableName) {
            DB::statement("DROP INDEX IF EXISTS {$tableName}_organization_id_index");
            DB::statement("CREATE INDEX {$tableName}_organization_id_index
                ON {$tableName} (organization_id)
                WHERE deleted_at IS NULL");
        }

        DB::statement('ALTER TABLE inventory_items DROP CONSTRAINT IF EXISTS inventory_items_sku_unique');
        DB::statement('DROP INDEX IF EXISTS inventory_items_sku_unique');
        DB::statement('CREATE UNIQUE INDEX inventory_items_organization_id_sku_unique
            ON inventory_items (organization_id, sku)
            WHERE deleted_at IS NULL AND sku IS NOT NULL');

        DB::statement('DROP INDEX IF EXISTS inventory_locations_external_id_external_type_unique');
        DB::statement('CREATE UNIQUE INDEX inventory_locations_org_external_unique
            ON inventory_locations (organization_id, external_id, external_type)
            WHERE deleted_at IS NULL');

        DB::statement('DROP INDEX IF EXISTS inventory_items_itemable_id_itemable_type_unique');
        DB::statement('CREATE UNIQUE INDEX inventory_items_org_itemable_unique
            ON inventory_items (organization_id, itemable_id, itemable_type)
            WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inventory_items_organization_id_sku_unique');
        DB::statement('DROP INDEX IF EXISTS inventory_locations_org_external_unique');
        DB::statement('DROP INDEX IF EXISTS inventory_items_org_itemable_unique');
        DB::statement('DROP INDEX IF EXISTS inventory_items_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS inventory_locations_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS inventory_stocks_organization_id_index');

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('inventory_locations', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        DB::statement('CREATE UNIQUE INDEX inventory_items_sku_unique
            ON inventory_items (sku)
            WHERE deleted_at IS NULL AND sku IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX inventory_locations_external_id_external_type_unique
            ON inventory_locations (external_id, external_type)
            WHERE deleted_at IS NULL');
    }
};
