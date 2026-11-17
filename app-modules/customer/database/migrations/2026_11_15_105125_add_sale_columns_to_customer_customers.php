<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The composite unique key lets a sale reference a customer of its own organization. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_customers', function (Blueprint $table): void {
            $table->date('payment_due_date')->nullable();
            $table->boolean('is_walk_in')->default(false);
            $table->unique(['organization_id', 'id'], 'customer_customers_organization_id_id_unique');
        });

        DB::statement('CREATE UNIQUE INDEX customer_customers_walk_in_unique ON customer_customers (organization_id) WHERE is_walk_in AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customer_customers_walk_in_unique');

        Schema::table('customer_customers', function (Blueprint $table): void {
            $table->dropUnique('customer_customers_organization_id_id_unique');
            $table->dropColumn(['payment_due_date', 'is_walk_in']);
        });
    }
};
