<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The sale settings of an organization: one row at most, created by its first
 * write. Without it a sale is written in the `counter` mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->unique()
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->string('default_mode', 30);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE sale_settings ADD CONSTRAINT sale_settings_default_mode_check CHECK (default_mode IN ('counter', 'order_invoiced_now', 'order_invoiced_on_delivery'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_settings');
    }
};
