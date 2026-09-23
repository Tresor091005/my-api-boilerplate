<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_files', function (Blueprint $table): void {
            $table->dropIndex('library_files_storage_status_index');
            $table->dropColumn('storage_status');
        });
    }

    public function down(): void
    {
        Schema::table('library_files', function (Blueprint $table): void {
            $table->string('storage_status', 20)->default('available');
            $table->index(['organization_id', 'storage_status'], 'library_files_storage_status_index');
        });
    }
};
