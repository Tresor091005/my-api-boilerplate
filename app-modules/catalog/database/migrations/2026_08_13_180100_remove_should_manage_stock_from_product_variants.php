<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->dropColumn('should_manage_stock');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->boolean('should_manage_stock')->default(false)->after('unit_group_id');
        });
    }
};
