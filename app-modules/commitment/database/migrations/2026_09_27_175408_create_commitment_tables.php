<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitment_service_commitments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('service_id');
            $table->uuid('customer_id');
            $table->string('client_email', 254);
            $table->string('public_reference', 32)->unique();
            $table->string('state', 40);
            $table->uuid('accepted_proposal_id')->nullable();
            $table->uuid('pending_lifecycle_request_id')->nullable();
            $table->timestamps();
            $table->foreign(['organization_id', 'service_id'], 'commitment_service_foreign')
                ->references(['organization_id', 'id'])->on('catalog_services')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('customer_customers')->restrictOnDelete();
            $table->index(['organization_id', 'state', 'created_at'], 'commitment_listing_index');
            $table->index(['organization_id', 'service_id'], 'commitment_service_lookup_index');
            $table->index('customer_id', 'commitment_customer_lookup_index');
        });

        Schema::create('commitment_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->foreignUuid('commitment_id')->constrained('commitment_service_commitments')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 150);
            $table->text('terms')->nullable();
            $table->string('state', 40);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'commitment_id', 'version'], 'commitment_proposals_version_unique');
            $table->index(['organization_id', 'commitment_id', 'state']);
            $table->index('commitment_id', 'commitment_proposals_parent_index');
        });

        Schema::create('commitment_deliverables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->foreignUuid('commitment_id')->constrained('commitment_service_commitments')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('display_unit_code', 50)->nullable();
            $table->string('execution_state', 40);
            $table->string('validation_state', 40);
            $table->uuid('accepted_evidence_id')->nullable();
            $table->uuid('current_evidence_id')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'commitment_id', 'created_at'], 'commitment_deliverables_listing_index');
            $table->index('commitment_id', 'commitment_deliverables_parent_index');
        });

        Schema::create('commitment_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->foreignUuid('commitment_id')->constrained('commitment_service_commitments')->cascadeOnDelete();
            $table->foreignUuid('deliverable_id')->constrained('commitment_deliverables')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('outcome', 40);
            $table->text('narrative')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->decimal('quantity', 18, 4)->nullable();
            $table->string('display_unit_code', 50)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['organization_id', 'deliverable_id', 'version'], 'commitment_evidence_version_unique');
            $table->index(['organization_id', 'commitment_id', 'submitted_at']);
            $table->index('commitment_id', 'commitment_evidence_parent_index');
            $table->index('deliverable_id', 'commitment_evidence_deliverable_index');
        });

        Schema::create('commitment_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->foreignUuid('commitment_id')->constrained('commitment_service_commitments')->cascadeOnDelete();
            $table->uuid('batch_id')->nullable();
            $table->string('subject_type', 40);
            $table->uuid('subject_id');
            $table->string('decision', 40);
            $table->text('comment')->nullable();
            $table->string('actor_email', 254);
            $table->timestamps();
            $table->index(['organization_id', 'commitment_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('commitment_id', 'commitment_reviews_parent_index');
        });

        Schema::create('commitment_audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->foreignUuid('commitment_id')->constrained('commitment_service_commitments')->cascadeOnDelete();
            $table->uuid('deliverable_id')->nullable();
            $table->string('event_type', 80);
            $table->string('actor_type', 40);
            $table->uuid('actor_id')->nullable();
            $table->string('actor_email', 254)->nullable();
            $table->string('from_state', 40)->nullable();
            $table->string('to_state', 40)->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->timestamp('created_at');
            $table->index(['organization_id', 'commitment_id', 'created_at'], 'commitment_audit_listing_index');
            $table->index(['organization_id', 'actor_type', 'actor_id'], 'commitment_audit_actor_index');
            $table->index('commitment_id', 'commitment_audit_parent_index');
        });

        Schema::create('commitment_guest_access_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->foreignUuid('commitment_id')->constrained('commitment_service_commitments')->cascadeOnDelete();
            $table->string('code_hash', 255);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['commitment_id', 'expires_at']);
            $table->index(['organization_id', 'commitment_id']);
        });

        Schema::create('commitment_guest_access_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->foreignUuid('commitment_id')->constrained('commitment_service_commitments')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['commitment_id', 'expires_at']);
            $table->index(['organization_id', 'commitment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitment_guest_access_sessions');
        Schema::dropIfExists('commitment_guest_access_challenges');
        Schema::dropIfExists('commitment_audit_events');
        Schema::dropIfExists('commitment_reviews');
        Schema::dropIfExists('commitment_evidence');
        Schema::dropIfExists('commitment_deliverables');
        Schema::dropIfExists('commitment_proposals');
        Schema::dropIfExists('commitment_service_commitments');
    }
};
