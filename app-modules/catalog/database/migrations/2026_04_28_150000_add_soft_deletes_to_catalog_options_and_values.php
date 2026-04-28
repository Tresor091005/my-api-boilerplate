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
        Schema::table('catalog_options', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS catalog_options_deleted_at_index ON catalog_options (deleted_at)');
        DB::statement('DROP INDEX IF EXISTS catalog_options_organization_id_index');
        DB::statement('ALTER TABLE catalog_options DROP CONSTRAINT IF EXISTS catalog_options_organization_id_name_unique');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_options_organization_id_index ON catalog_options (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS catalog_options_organization_id_name_unique ON catalog_options (organization_id, name) WHERE deleted_at IS NULL');

        Schema::table('catalog_option_values', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS catalog_option_values_deleted_at_index ON catalog_option_values (deleted_at)');
        DB::statement('DROP INDEX IF EXISTS catalog_option_values_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_option_values_option_id_index');
        DB::statement('ALTER TABLE catalog_option_values DROP CONSTRAINT IF EXISTS catalog_option_values_organization_id_option_id_value_unique');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_option_values_organization_id_index ON catalog_option_values (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_option_values_option_id_index ON catalog_option_values (option_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS catalog_option_values_organization_id_option_id_value_unique ON catalog_option_values (organization_id, option_id, value) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS catalog_option_values_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_option_values_option_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_option_values_organization_id_option_id_value_unique');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_option_values_organization_id_index ON catalog_option_values (organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_option_values_option_id_index ON catalog_option_values (option_id)');
        DB::statement('ALTER TABLE catalog_option_values ADD CONSTRAINT catalog_option_values_organization_id_option_id_value_unique UNIQUE (organization_id, option_id, value)');
        DB::statement('DROP INDEX IF EXISTS catalog_option_values_deleted_at_index');

        Schema::table('catalog_option_values', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        DB::statement('DROP INDEX IF EXISTS catalog_options_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_options_organization_id_name_unique');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_options_organization_id_index ON catalog_options (organization_id)');
        DB::statement('ALTER TABLE catalog_options ADD CONSTRAINT catalog_options_organization_id_name_unique UNIQUE (organization_id, name)');
        DB::statement('DROP INDEX IF EXISTS catalog_options_deleted_at_index');

        Schema::table('catalog_options', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
