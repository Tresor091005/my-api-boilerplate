<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX iam_organization_members_organization_id_id_unique
            ON iam_organization_members (organization_id, id)
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS iam_organization_members_organization_id_id_unique');
    }
};
