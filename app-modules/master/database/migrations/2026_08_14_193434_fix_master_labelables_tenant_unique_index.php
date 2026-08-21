<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE master_labelables
            DROP CONSTRAINT master_labelables_label_id_labelable_type_labelable_id_unique');

        DB::statement('CREATE UNIQUE INDEX master_labelables_org_label_labelable_unique
            ON master_labelables (organization_id, label_id, labelable_type, labelable_id)');

        DB::statement('DROP INDEX master_labelables_labelable_type_labelable_id_index');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS master_labelables_org_label_labelable_unique');
        DB::statement('DROP INDEX IF EXISTS master_labelables_organization_label_id_labelable_type_labelable_id_');

        DB::statement('ALTER TABLE master_labelables
            ADD CONSTRAINT master_labelables_label_id_labelable_type_labelable_id_unique
            UNIQUE (label_id, labelable_type, labelable_id)');

        DB::statement('CREATE INDEX IF NOT EXISTS master_labelables_labelable_type_labelable_id_index
            ON master_labelables (labelable_type, labelable_id)');
    }
};
