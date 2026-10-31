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
        Schema::create('billing_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->foreignUuid('plan_version_id')
                ->constrained('billing_plan_versions')
                ->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement('ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_coverage_check CHECK (ends_at > starts_at)');
        DB::statement(
            'ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_no_overlap '
            .'EXCLUDE USING gist (organization_id WITH =, tsrange(starts_at, ends_at) WITH &&) '
            .'WHERE (deleted_at IS NULL)'
        );
        DB::statement('CREATE INDEX billing_subscriptions_organization_id_starts_at_index ON billing_subscriptions (organization_id, starts_at) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX billing_subscriptions_plan_version_id_index ON billing_subscriptions (plan_version_id) WHERE deleted_at IS NULL');

        Schema::create('billing_trials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->unique()
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->foreignUuid('plan_version_id')
                ->index()
                ->constrained('billing_plan_versions')
                ->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE billing_trials ADD CONSTRAINT billing_trials_coverage_check CHECK (ends_at >= starts_at)');

        Schema::create('billing_checkouts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->foreignUuid('plan_version_id')
                ->constrained('billing_plan_versions')
                ->restrictOnDelete();
            $table->foreignUuid('subscription_id')
                ->nullable()
                ->constrained('billing_subscriptions')
                ->restrictOnDelete();
            $table->string('action', 20);
            $table->unsignedSmallInteger('periods');
            $table->bigInteger('total_amount');
            $table->string('currency_code', 3);
            $table->timestamp('covers_from');
            $table->timestamp('covers_until');
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->foreign('currency_code')->references('code')->on('master_currencies')->restrictOnDelete();
            $table->index(['organization_id', 'started_at']);
            $table->index('plan_version_id');
            $table->index('subscription_id');
            $table->index('currency_code');
        });

        DB::statement('ALTER TABLE billing_checkouts ADD CONSTRAINT billing_checkouts_total_amount_check CHECK (total_amount >= 0)');
        DB::statement('ALTER TABLE billing_checkouts ADD CONSTRAINT billing_checkouts_periods_check CHECK (periods >= 1)');
        DB::statement(
            'CREATE UNIQUE INDEX billing_checkouts_one_pending '
            .'ON billing_checkouts (organization_id) '
            ."WHERE status = 'pending'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_checkouts');
        Schema::dropIfExists('billing_trials');
        Schema::dropIfExists('billing_subscriptions');
    }
};
