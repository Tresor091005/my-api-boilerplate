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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('billing_plan_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('billing_plans')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20);
            $table->bigInteger('price');
            $table->string('currency_code', 3)->nullable();
            $table->unsignedSmallInteger('billing_interval');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['plan_id', 'version']);
            $table->index(['plan_id', 'status']);
            $table->foreign('currency_code')->references('code')->on('master_currencies')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE billing_plan_versions ADD CONSTRAINT billing_plan_versions_price_check CHECK (price >= 0)');
        DB::statement('CREATE INDEX billing_plan_versions_currency_code_index ON billing_plan_versions (currency_code) WHERE deleted_at IS NULL');
        DB::statement(
            'CREATE UNIQUE INDEX billing_plan_versions_one_published '
            .'ON billing_plan_versions (plan_id) '
            ."WHERE status = 'published' AND deleted_at IS NULL"
        );

        Schema::create('billing_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('key')->unique();
            $table->text('name');
            $table->string('type', 20);
            $table->text('resolver_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('billing_plan_version_features', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_version_id')
                ->constrained('billing_plan_versions')
                ->restrictOnDelete();
            $table->foreignUuid('feature_id')
                ->constrained('billing_features')
                ->restrictOnDelete();
            $table->unsignedInteger('allowance')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['plan_version_id', 'feature_id']);
        });

        DB::statement('CREATE INDEX billing_plan_version_features_feature_id_index ON billing_plan_version_features (feature_id) WHERE deleted_at IS NULL');

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
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->unsignedTinyInteger('billing_anchor_day');
            $table->string('collection_method', 20);
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->text('provider')->nullable();
            $table->text('provider_subscription_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status']);
            $table->unique(['provider', 'provider_subscription_id']);
        });

        DB::statement('ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_anchor_day_check CHECK (billing_anchor_day between 1 and 31)');
        DB::statement('ALTER TABLE billing_subscriptions ADD CONSTRAINT billing_subscriptions_period_check CHECK (current_period_end > current_period_start)');
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
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('billing_plan_version_features');
        Schema::dropIfExists('billing_features');
        Schema::dropIfExists('billing_plan_versions');
        Schema::dropIfExists('billing_plans');
    }
};
