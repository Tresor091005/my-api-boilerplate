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
        Schema::create('master_currencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 3)->unique()->index();
            $table->text('name');
            $table->string('symbol', 10);
            $table->integer('precision')->default(2);
            $table->timestamps();
        });

        Schema::create('master_unit_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX master_unit_groups_name_unique ON master_unit_groups (name) WHERE deleted_at IS NULL');

        Schema::create('master_units', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique()->index();
            $table->text('name');
            $table->string('symbol', 10)->nullable();
            $table->unsignedInteger('ratio');
            $table->foreignUuid('group_id')
                ->index()
                ->constrained('master_unit_groups')
                ->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX master_units_group_id_ratio_unique ON master_units (group_id, ratio) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_units');
        Schema::dropIfExists('master_unit_groups');
        Schema::dropIfExists('master_currencies');
    }
};
