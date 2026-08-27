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
        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->jsonb('exchange_metadata')->nullable()->after('metadata');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->jsonb('exchange_metadata')->nullable()->after('metadata');
        });

        DB::statement('ALTER TABLE inventory_stocks ALTER COLUMN currency_code SET NOT NULL');
        DB::statement('ALTER TABLE inventory_movements ALTER COLUMN currency_code SET NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inventory_stocks ALTER COLUMN currency_code DROP NOT NULL');
        DB::statement('ALTER TABLE inventory_movements ALTER COLUMN currency_code DROP NOT NULL');

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn('exchange_metadata');
        });

        Schema::table('inventory_stocks', function (Blueprint $table): void {
            $table->dropColumn('exchange_metadata');
        });
    }
};
