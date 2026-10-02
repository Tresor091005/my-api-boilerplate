<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iam_users', function (Blueprint $table): void {
            $table->foreignUuid('default_member_role_id')->nullable()->index()
                ->constrained('iam_member_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('iam_users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_member_role_id');
        });
    }
};
