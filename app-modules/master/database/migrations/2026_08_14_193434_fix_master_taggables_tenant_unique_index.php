<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE master_taggables
            DROP CONSTRAINT master_taggables_tag_id_taggable_type_taggable_id_unique');

        DB::statement('CREATE UNIQUE INDEX master_taggables_org_tag_taggable_unique
            ON master_taggables (organization_id, tag_id, taggable_type, taggable_id)');

        DB::statement('DROP INDEX master_taggables_taggable_type_taggable_id_index');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS master_taggables_org_tag_taggable_unique');
        DB::statement('DROP INDEX IF EXISTS master_taggables_organization_tag_id_taggable_type_taggable_id_');

        DB::statement('ALTER TABLE master_taggables
            ADD CONSTRAINT master_taggables_tag_id_taggable_type_taggable_id_unique
            UNIQUE (tag_id, taggable_type, taggable_id)');

        DB::statement('CREATE INDEX IF NOT EXISTS master_taggables_taggable_type_taggable_id_index
            ON master_taggables (taggable_type, taggable_id)');
    }
};
