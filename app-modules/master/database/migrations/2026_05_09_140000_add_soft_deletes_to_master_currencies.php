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
        Schema::table('master_currencies', function (Blueprint $table): void {
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX IF NOT EXISTS master_currencies_deleted_at_index ON master_currencies (deleted_at)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS master_currencies_deleted_at_index');

        Schema::table('master_currencies', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
