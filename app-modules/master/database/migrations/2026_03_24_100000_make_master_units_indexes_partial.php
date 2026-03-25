<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('master_units', function (Blueprint $table): void {
            $table->dropIndex('master_units_group_id_index');
        });

        DB::statement('CREATE INDEX master_units_group_id_index ON master_units (group_id) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_units', function (Blueprint $table): void {
            $table->dropIndex('master_units_group_id_index');
            $table->index('group_id');
        });
    }
};
