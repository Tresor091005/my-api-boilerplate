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
        Schema::create('organization_exchange_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->cascadeOnDelete();
            $table->string('source_currency_code', 3);
            $table->string('target_currency_code', 3);
            $table->string('context', 50)->default('default');
            $table->decimal('rate', 30, 12);
            $table->timestamp('effective_at');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('source_currency_code')
                ->references('code')
                ->on('master_currencies')
                ->restrictOnDelete();
            $table->foreign('target_currency_code')
                ->references('code')
                ->on('master_currencies')
                ->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX organization_exchange_rates_identity_unique ON organization_exchange_rates (organization_id, source_currency_code, target_currency_code, context, effective_at) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX organization_exchange_rates_deleted_at_index ON organization_exchange_rates (deleted_at)');
        DB::statement('CREATE INDEX organization_exchange_rates_source_currency_code_index ON organization_exchange_rates (source_currency_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX organization_exchange_rates_target_currency_code_index ON organization_exchange_rates (target_currency_code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_exchange_rates');
    }
};
