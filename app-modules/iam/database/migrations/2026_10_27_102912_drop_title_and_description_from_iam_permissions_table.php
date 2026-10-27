<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iam_permissions', function (Blueprint $table): void {
            $table->dropColumn(['title', 'description']);
        });
    }

    public function down(): void
    {
        Schema::table('iam_permissions', function (Blueprint $table): void {
            $table->string('title')->nullable();
            $table->string('description')->nullable();
        });
    }
};
