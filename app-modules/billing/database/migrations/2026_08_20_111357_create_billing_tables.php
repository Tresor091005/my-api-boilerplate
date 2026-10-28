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
        Schema::create('billing_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('code')->unique();
            $table->text('name');
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
        });

        Schema::create('billing_plan_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('billing_plans')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20);
            $table->bigInteger('price');
            $table->string('currency_code', 3);
            $table->unsignedSmallInteger('billing_interval');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['plan_id', 'version']);
            $table->foreign('currency_code')->references('code')->on('master_currencies')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE billing_plan_versions ADD CONSTRAINT billing_plan_versions_price_check CHECK (price >= 0)');
        DB::statement('CREATE INDEX billing_plan_versions_plan_id_status_index ON billing_plan_versions (plan_id, status) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX billing_plan_versions_currency_code_index ON billing_plan_versions (currency_code) WHERE deleted_at IS NULL');
        DB::statement(
            'CREATE UNIQUE INDEX billing_plan_versions_one_published '
            .'ON billing_plan_versions (plan_id) '
            ."WHERE status = 'published' AND deleted_at IS NULL"
        );
        DB::statement(
            'CREATE UNIQUE INDEX billing_plan_versions_one_draft '
            .'ON billing_plan_versions (plan_id) '
            ."WHERE status = 'draft' AND deleted_at IS NULL"
        );

        Schema::create('billing_plan_version_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_version_id')
                ->constrained('billing_plan_versions')
                ->restrictOnDelete();
            $table->text('feature_key');
            $table->string('feature_type', 20);
            $table->unsignedInteger('allowance')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['plan_version_id', 'feature_key'], 'billing_plan_version_features_version_key_unique');
        });

        DB::statement('CREATE INDEX billing_plan_version_features_feature_key_type_index ON billing_plan_version_features (feature_key, feature_type) WHERE deleted_at IS NULL');

        Schema::create('billing_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->restrictOnDelete();
            $table->foreignUuid('plan_version_id')
                ->constrained('billing_plan_versions')
                ->restrictOnDelete();
            $table->boolean('is_current')->default(false);
            $table->string('status', 20);
            $table->timestamp('current_period_starts_at');
            $table->timestamp('current_period_ends_at');
            $table->unsignedTinyInteger('billing_anchor_day');
            $table->string('collection_method', 20);
            $table->boolean('is_cancellation_scheduled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->text('provider')->nullable();
            $table->text('provider_subscription_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_anchor_day_check CHECK (billing_anchor_day between 1 and 31)');
        DB::statement('ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_period_check CHECK (current_period_ends_at > current_period_starts_at)');
        DB::statement('CREATE INDEX billing_subscriptions_organization_id_status_index ON billing_subscriptions (organization_id, status) WHERE deleted_at IS NULL');
        DB::statement(
            'CREATE UNIQUE INDEX billing_subscriptions_provider_subscription_unique '
            .'ON billing_subscriptions (provider, provider_subscription_id) '
            .'WHERE deleted_at IS NULL'
        );
        DB::statement('CREATE INDEX billing_subscriptions_plan_version_id_index ON billing_subscriptions (plan_version_id) WHERE deleted_at IS NULL');
        DB::statement(
            'CREATE UNIQUE INDEX billing_subscriptions_one_current '
            .'ON billing_subscriptions (organization_id) '
            .'WHERE is_current = true AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS billing_subscriptions_one_current');
        DB::statement('DROP INDEX IF EXISTS billing_plan_versions_one_published');
        DB::statement('DROP INDEX IF EXISTS billing_plan_versions_one_draft');
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('billing_plan_version_features');
        Schema::dropIfExists('billing_plan_versions');
        Schema::dropIfExists('billing_plans');
    }
};
