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
        Schema::table('master_taggables', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable()->after('id');
        });

        DB::statement('UPDATE master_taggables AS taggables
            SET organization_id = tags.organization_id
            FROM master_tags AS tags
            WHERE tags.id = taggables.tag_id');

        DB::statement('ALTER TABLE master_taggables ALTER COLUMN organization_id SET NOT NULL');

        DB::statement('CREATE UNIQUE INDEX master_tags_organization_id_id_unique
            ON master_tags (organization_id, id)');

        Schema::table('master_taggables', function (Blueprint $table): void {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organization_organizations')
                ->onDelete('restrict');
            $table->foreign(['organization_id', 'tag_id'], 'master_taggables_organization_tag_id_foreign')
                ->references(['organization_id', 'id'])
                ->on('master_tags')
                ->onDelete('cascade');
            $table->index(
                ['organization_id', 'taggable_type', 'taggable_id'],
                'master_taggables_organization_taggable_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('master_taggables', function (Blueprint $table): void {
            $table->dropForeign('master_taggables_organization_tag_id_foreign');
            $table->dropForeign(['organization_id']);
            $table->dropIndex('master_taggables_organization_taggable_index');
            $table->dropColumn('organization_id');
        });

        DB::statement('DROP INDEX IF EXISTS master_tags_organization_id_id_unique');
    }
};
