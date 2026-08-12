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
        Schema::table('catalog_categories', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS catalog_categories_deleted_at_index ON catalog_categories (deleted_at)');
        DB::statement('DROP INDEX IF EXISTS catalog_categories_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_categories_parent_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_categories_organization_id_index ON catalog_categories (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_categories_parent_id_index ON catalog_categories (parent_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS catalog_categories_organization_id_index');
        DB::statement('DROP INDEX IF EXISTS catalog_categories_parent_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_categories_organization_id_index ON catalog_categories (organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS catalog_categories_parent_id_index ON catalog_categories (parent_id)');
        DB::statement('DROP INDEX IF EXISTS catalog_categories_deleted_at_index');

        Schema::table('catalog_categories', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
