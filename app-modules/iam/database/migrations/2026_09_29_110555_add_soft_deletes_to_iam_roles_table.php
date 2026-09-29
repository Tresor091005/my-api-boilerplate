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
        Schema::table('iam_roles', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE iam_roles DROP CONSTRAINT iam_roles_guard_name_name_team_id_unique');
        DB::statement('CREATE UNIQUE INDEX iam_roles_guard_name_name_team_id_unique ON iam_roles (guard_name, name, team_id) WHERE deleted_at IS NULL');
        DB::statement('DROP INDEX iam_roles_team_id_index');
        DB::statement('CREATE INDEX iam_roles_team_id_index ON iam_roles (team_id) WHERE deleted_at IS NULL');
    }

    public function down(): void {}
};
