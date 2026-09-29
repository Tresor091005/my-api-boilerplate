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
        Schema::table('organization_organizations', function (Blueprint $table): void {
            $table->foreignUuid('owner_id')->after('name')->constrained('iam_users')->restrictOnDelete();
        });

        DB::statement('CREATE INDEX organization_organizations_owner_id_index ON organization_organizations (owner_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX organization_organizations_owner_id_index');

        Schema::table('organization_organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
