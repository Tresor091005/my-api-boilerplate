<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A NULL tax profile means no tax applies to the item; it never means "not configured yet".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table): void {
            $table->uuid('tax_profile_id')->nullable();

            $table->foreign(['organization_id', 'tax_profile_id'], 'catalog_items_tax_profile_foreign')
                ->references(['organization_id', 'id'])
                ->on('tax_profiles')
                ->restrictOnDelete();
        });

        DB::statement('CREATE INDEX catalog_items_organization_id_tax_profile_id_index ON catalog_items (organization_id, tax_profile_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS catalog_items_organization_id_tax_profile_id_index');

        Schema::table('catalog_items', function (Blueprint $table): void {
            $table->dropForeign('catalog_items_tax_profile_foreign');
            $table->dropColumn('tax_profile_id');
        });
    }
};
