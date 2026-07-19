<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->boolean('is_expirable')->default(false)->after('deduction_strategy');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropColumn('is_expirable');
        });
    }
};
