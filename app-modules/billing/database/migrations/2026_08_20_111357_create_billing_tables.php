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
            $table->boolean('is_trial')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
        });

        DB::statement('CREATE UNIQUE INDEX billing_plans_one_trial ON billing_plans (is_trial) WHERE is_trial AND deleted_at IS NULL');

        Schema::create('billing_plan_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('billing_plans')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20);
            $table->bigInteger('price');
            $table->string('currency_code', 3);
            $table->unsignedSmallInteger('billing_interval_months');
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
            $table->jsonb('values')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['plan_version_id', 'feature_key'], 'billing_plan_version_features_version_key_unique');
        });

        DB::statement('CREATE INDEX billing_plan_version_features_feature_key_type_index ON billing_plan_version_features (feature_key, feature_type) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS billing_plan_versions_one_published');
        DB::statement('DROP INDEX IF EXISTS billing_plan_versions_one_draft');
        Schema::dropIfExists('billing_plan_version_features');
        Schema::dropIfExists('billing_plan_versions');
        Schema::dropIfExists('billing_plans');
    }
};
