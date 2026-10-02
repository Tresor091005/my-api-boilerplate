<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX iam_users_default_member_role_id_index');
        DB::statement('CREATE INDEX iam_users_default_member_role_id_index ON iam_users (default_member_role_id) WHERE deleted_at IS NULL');
    }

    public function down(): void {}
};
