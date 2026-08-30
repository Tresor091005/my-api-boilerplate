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
        Schema::create('customer_customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->string('type', 50);
            $table->string('name', 100);
            $table->string('identification_number', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX customer_customers_organization_id_index ON customer_customers (organization_id) WHERE deleted_at IS NULL');
        DB::statement("ALTER TABLE customer_customers ADD CONSTRAINT customer_customers_company_identification_check CHECK (type <> 'company' OR identification_number IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_customers');
    }
};
