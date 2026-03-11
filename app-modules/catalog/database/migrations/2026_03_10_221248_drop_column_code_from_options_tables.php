<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catalog_options', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->unique('name');

            $table->dropColumn(['code']);
        });

        Schema::table('catalog_option_values', function (Blueprint $table): void {
            $table->dropUnique(['option_id', 'code']);
            $table->unique(['option_id', 'value']);

            $table->dropIndex(['code']);
            $table->dropColumn(['code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
