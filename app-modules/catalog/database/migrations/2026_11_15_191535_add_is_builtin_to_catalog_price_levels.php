<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The built-in price level: the one level an organization always has, so a sale always has a level to choose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_price_levels', function (Blueprint $table): void {
            $table->boolean('is_builtin')->default(false);
        });

        DB::statement('CREATE UNIQUE INDEX catalog_price_levels_builtin_unique ON catalog_price_levels (organization_id) WHERE is_builtin AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS catalog_price_levels_builtin_unique');

        Schema::table('catalog_price_levels', function (Blueprint $table): void {
            $table->dropColumn('is_builtin');
        });
    }
};
