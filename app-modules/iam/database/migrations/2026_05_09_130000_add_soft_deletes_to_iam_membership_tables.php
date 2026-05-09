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
        Schema::table('iam_organization_members', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS iam_organization_members_deleted_at_index ON iam_organization_members (deleted_at)');

        DB::statement('DROP INDEX IF EXISTS iam_organization_members_user_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_organization_members_user_id_index ON iam_organization_members (user_id) WHERE deleted_at IS NULL');

        DB::statement('DROP INDEX IF EXISTS iam_organization_members_organization_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_organization_members_organization_id_index ON iam_organization_members (organization_id) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE iam_organization_members DROP CONSTRAINT IF EXISTS iam_organization_members_user_id_organization_id_unique');
        DB::statement('DROP INDEX IF EXISTS iam_organization_members_user_id_organization_id_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS iam_organization_members_user_id_organization_id_unique ON iam_organization_members (user_id, organization_id) WHERE deleted_at IS NULL');

        Schema::table('iam_member_roles', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS iam_member_roles_deleted_at_index ON iam_member_roles (deleted_at)');

        DB::statement('DROP INDEX IF EXISTS iam_member_roles_organization_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_member_roles_organization_id_index ON iam_member_roles (organization_id) WHERE deleted_at IS NULL');

        DB::statement('DROP INDEX IF EXISTS iam_member_roles_member_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_member_roles_member_id_index ON iam_member_roles (member_id) WHERE deleted_at IS NULL');

        DB::statement('DROP INDEX IF EXISTS iam_member_roles_role_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_member_roles_role_id_index ON iam_member_roles (role_id) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE iam_member_roles DROP CONSTRAINT IF EXISTS iam_member_roles_organization_id_member_id_role_id_unique');
        DB::statement('DROP INDEX IF EXISTS iam_member_roles_organization_id_member_id_role_id_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS iam_member_roles_organization_id_member_id_role_id_unique ON iam_member_roles (organization_id, member_id, role_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS iam_member_roles_organization_id_member_id_role_id_unique');
        DB::statement('ALTER TABLE iam_member_roles ADD CONSTRAINT iam_member_roles_organization_id_member_id_role_id_unique UNIQUE (organization_id, member_id, role_id)');

        DB::statement('DROP INDEX IF EXISTS iam_member_roles_role_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_member_roles_role_id_index ON iam_member_roles (role_id)');

        DB::statement('DROP INDEX IF EXISTS iam_member_roles_member_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_member_roles_member_id_index ON iam_member_roles (member_id)');

        DB::statement('DROP INDEX IF EXISTS iam_member_roles_organization_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_member_roles_organization_id_index ON iam_member_roles (organization_id)');

        DB::statement('DROP INDEX IF EXISTS iam_member_roles_deleted_at_index');

        Schema::table('iam_member_roles', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        DB::statement('DROP INDEX IF EXISTS iam_organization_members_user_id_organization_id_unique');
        DB::statement('ALTER TABLE iam_organization_members ADD CONSTRAINT iam_organization_members_user_id_organization_id_unique UNIQUE (user_id, organization_id)');

        DB::statement('DROP INDEX IF EXISTS iam_organization_members_organization_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_organization_members_organization_id_index ON iam_organization_members (organization_id)');

        DB::statement('DROP INDEX IF EXISTS iam_organization_members_user_id_index');
        DB::statement('CREATE INDEX IF NOT EXISTS iam_organization_members_user_id_index ON iam_organization_members (user_id)');

        DB::statement('DROP INDEX IF EXISTS iam_organization_members_deleted_at_index');

        Schema::table('iam_organization_members', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
