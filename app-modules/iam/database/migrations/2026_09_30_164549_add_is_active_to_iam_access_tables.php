<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['iam_organization_members', 'iam_member_roles', 'iam_roles'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->boolean('is_active')->default(true);
            });
        }
    }

    public function down(): void
    {
        foreach (['iam_organization_members', 'iam_member_roles', 'iam_roles'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }
    }
};
