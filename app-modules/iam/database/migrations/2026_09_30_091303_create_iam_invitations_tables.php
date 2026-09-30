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
        Schema::create('iam_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations');
            $table->string('email', 254);
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'email']);
            $table->unique(['organization_id', 'id']);
        });
        DB::statement('CREATE INDEX iam_invitations_organization_id_index ON iam_invitations (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX iam_invitations_organization_created_index ON iam_invitations (organization_id, created_at, id) WHERE deleted_at IS NULL');
        Schema::create('iam_invitation_roles', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->index()->constrained('organization_organizations');
            $table->uuid('invitation_id');
            $table->foreignUuid('role_id')->index()->constrained('iam_roles');
            $table->primary(['organization_id', 'invitation_id', 'role_id']);
            $table->foreign(['organization_id', 'invitation_id'])
                ->references(['organization_id', 'id'])->on('iam_invitations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iam_invitation_roles');
        Schema::dropIfExists('iam_invitations');
    }
};
