<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iam_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('first_name');
            $table->text('last_name');
            $table->text('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->text('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
        });

        Schema::create('iam_organization_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained('iam_users')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->index()->constrained('organization_organizations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'organization_id'], 'iam_organization_members_user_id_organization_id_unique');
        });

        Schema::create('iam_member_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->index()->constrained('organization_organizations')->cascadeOnDelete();
            $table->foreignUuid('member_id')->index()->constrained('iam_organization_members')->cascadeOnDelete();
            $table->foreignUuid('role_id')->index()->constrained('iam_roles')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'member_id', 'role_id'], 'iam_member_roles_organization_id_member_id_role_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iam_member_roles');
        Schema::dropIfExists('iam_organization_members');
        Schema::dropIfExists('iam_users');
    }
};
