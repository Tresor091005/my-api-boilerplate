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
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->jsonb('metadata')->nullable()->after('expiration_date');

            // Drop existing foreign key and constraint
            $table->dropForeign(['stock_id']);

            // keep stock_id even after deletion to allow reconstruction
            $table->foreign('stock_id')
                ->references('id')
                ->on('inventory_stocks')
                ->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign(['stock_id']);

            $table->foreign('stock_id')
                ->references('id')
                ->on('inventory_stocks')
                ->onDelete('restrict');

            $table->dropColumn('metadata');
        });
    }
};
