<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            // Remove unit_code and add unit_group_id
            $table->dropForeign(['unit_code']);
            $table->dropColumn('unit_code');

            $table->foreignUuid('unit_group_id')
                ->after('sku')
                ->index()
                ->constrained('master_unit_groups')
                ->onDelete('restrict');

            // Remove min_qt, max_qt and step
            $table->dropColumn(['min_quantity', 'max_quantity', 'step']);

            // Remove is_default
            $table->dropColumn('is_default');

            // Replace is_stockable by manage_stock
            $table->boolean('manage_stock')->default(true)->after('unit_group_id');
            $table->dropColumn('is_stockable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_product_variants', function (Blueprint $table): void {
            $table->dropForeign(['unit_group_id']);
            $table->dropColumn(['unit_group_id', 'manage_stock']);

            $table->string('unit_code')
                ->nullable()
                ->after('sku')
                ->index();
            $table->foreign('unit_code')
                ->references('code')
                ->on('master_units')
                ->onDelete('restrict');

            $table->integer('min_quantity')->default(1)->after('unit_code');
            $table->integer('max_quantity')->nullable()->after('min_quantity');
            $table->integer('step')->default(1)->after('max_quantity');

            $table->boolean('is_default')->default(false)->after('step');
            $table->boolean('is_stockable')->default(true)->after('is_default');
        });
    }
};
