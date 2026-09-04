<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_commitments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('service_id');
            $table->uuid('sale_line_id');
            $table->string('status', 20);
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'sale_line_id'], 'service_commitments_sale_line_unique');
            $table->unique(['organization_id', 'id'], 'service_commitments_organization_id_id_unique');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'service_id'], 'service_commitments_service_index');
            $table->foreign(['organization_id', 'service_id'], 'service_commitments_service_foreign')
                ->references(['organization_id', 'id'])
                ->on('catalog_services')
                ->restrictOnDelete();
        });

        Schema::create('service_deliverables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('commitment_id');
            $table->text('name');
            $table->unsignedInteger('position');
            $table->string('status', 20);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'commitment_id', 'position'], 'service_deliverables_position_unique');
            $table->unique(['organization_id', 'id'], 'service_deliverables_organization_id_id_unique');
            $table->index(['organization_id', 'commitment_id'], 'service_deliverables_commitment_index');
            $table->foreign(['organization_id', 'commitment_id'], 'service_deliverables_commitment_foreign')
                ->references(['organization_id', 'id'])
                ->on('service_commitments')
                ->cascadeOnDelete();
        });

        Schema::create('service_evidences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('deliverable_id');
            $table->string('status', 20);
            $table->uuid('token')->unique();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('verifier_identifier')->nullable();
            $table->jsonb('snapshot');
            $table->timestamps();
            $table->index(['organization_id', 'deliverable_id'], 'service_evidences_deliverable_index');
            $table->foreign(['organization_id', 'deliverable_id'], 'service_evidences_deliverable_foreign')
                ->references(['organization_id', 'id'])
                ->on('service_deliverables')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_evidences');
        Schema::dropIfExists('service_deliverables');
        Schema::dropIfExists('service_commitments');
    }
};
