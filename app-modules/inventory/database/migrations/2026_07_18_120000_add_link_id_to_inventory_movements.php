<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->uuid('link_id')->nullable()->after('transaction_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex(['link_id']);
            $table->dropColumn('link_id');
        });
    }
};
