<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Stockable follows from the item type, so the column was a redundant copy. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table): void {
            $table->dropColumn('is_stockable');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table): void {
            $table->boolean('is_stockable')->default(true);
        });

        DB::table('catalog_items')->where('item_type', 'catalog_service')->update(['is_stockable' => false]);
    }
};
