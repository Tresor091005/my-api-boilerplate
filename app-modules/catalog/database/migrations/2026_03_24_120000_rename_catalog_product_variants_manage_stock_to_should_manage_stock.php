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
        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->renameColumn('manage_stock', 'should_manage_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->renameColumn('should_manage_stock', 'manage_stock');
        });
    }
};
