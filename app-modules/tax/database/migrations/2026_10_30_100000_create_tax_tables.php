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
        Schema::create('tax_taxes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->string('calculation_type', 20);
            $table->string('fixed_mode', 20)->nullable();
            $table->foreignUuid('tax_unit_id')->nullable()->constrained('master_units')->restrictOnDelete();
            $table->string('base_type', 30);
            $table->boolean('is_progressive');
            $table->string('bracket_mode', 20)->nullable();
            $table->string('rounding_mode', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'id'], 'tax_taxes_organization_id_id_unique');
        });

        DB::statement('CREATE UNIQUE INDEX tax_taxes_organization_id_code_unique ON tax_taxes (organization_id, code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX tax_taxes_tax_unit_id_index ON tax_taxes (tax_unit_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX tax_taxes_deleted_at_index ON tax_taxes (deleted_at)');
        DB::statement("ALTER TABLE tax_taxes ADD CONSTRAINT tax_taxes_fixed_mode_check CHECK ((calculation_type = 'percentage' AND fixed_mode IS NULL) OR (calculation_type = 'fixed' AND fixed_mode IS NOT NULL))");
        DB::statement("ALTER TABLE tax_taxes ADD CONSTRAINT tax_taxes_tax_unit_check CHECK ((fixed_mode IS NOT DISTINCT FROM 'per_tax_unit') = (tax_unit_id IS NOT NULL))");
        DB::statement("ALTER TABLE tax_taxes ADD CONSTRAINT tax_taxes_bracket_mode_check CHECK ((is_progressive AND calculation_type = 'percentage') = (bracket_mode IS NOT NULL))");

        Schema::create('tax_brackets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('tax_id');
            $table->decimal('base_min', 24, 6);
            $table->decimal('base_max', 24, 6)->nullable();
            $table->decimal('value', 24, 6);
            $table->decimal('tax_amount_min', 24, 6)->nullable();
            $table->decimal('tax_amount_max', 24, 6)->nullable();
            $table->timestamps();

            $table->foreign(['organization_id', 'tax_id'], 'tax_brackets_tax_foreign')
                ->references(['organization_id', 'id'])->on('tax_taxes')->cascadeOnDelete();
            $table->unique(['organization_id', 'id'], 'tax_brackets_organization_id_id_unique');
        });

        // Deferred so a bracket set can be rewritten in place without transient collisions.
        DB::statement('ALTER TABLE tax_brackets ADD CONSTRAINT tax_brackets_tax_base_min_unique UNIQUE (organization_id, tax_id, base_min) DEFERRABLE INITIALLY DEFERRED');

        DB::statement('ALTER TABLE tax_brackets ADD CONSTRAINT tax_brackets_base_check CHECK (base_min >= 0 AND (base_max IS NULL OR base_max > base_min))');
        DB::statement('ALTER TABLE tax_brackets ADD CONSTRAINT tax_brackets_value_check CHECK (value >= 0)');
        DB::statement('ALTER TABLE tax_brackets ADD CONSTRAINT tax_brackets_amount_limits_check CHECK ((tax_amount_min IS NULL OR tax_amount_min >= 0) AND (tax_amount_max IS NULL OR tax_amount_max >= 0) AND (tax_amount_min IS NULL OR tax_amount_max IS NULL OR tax_amount_min <= tax_amount_max))');

        Schema::create('tax_dependencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('tax_id');
            $table->uuid('depends_on_tax_id');
            $table->string('operator', 20);
            $table->timestamps();

            $table->foreign(['organization_id', 'tax_id'], 'tax_dependencies_tax_foreign')
                ->references(['organization_id', 'id'])->on('tax_taxes')->cascadeOnDelete();
            $table->foreign(['organization_id', 'depends_on_tax_id'], 'tax_dependencies_depends_on_tax_foreign')
                ->references(['organization_id', 'id'])->on('tax_taxes')->restrictOnDelete();
            $table->unique(['organization_id', 'tax_id', 'depends_on_tax_id'], 'tax_dependencies_pair_unique');
            $table->index(['organization_id', 'depends_on_tax_id'], 'tax_dependencies_depends_on_tax_index');
        });

        DB::statement('ALTER TABLE tax_dependencies ADD CONSTRAINT tax_dependencies_not_self_check CHECK (tax_id <> depends_on_tax_id)');

        Schema::create('tax_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'id'], 'tax_profiles_organization_id_id_unique');
        });

        DB::statement('CREATE UNIQUE INDEX tax_profiles_organization_id_code_unique ON tax_profiles (organization_id, code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX tax_profiles_deleted_at_index ON tax_profiles (deleted_at)');

        Schema::create('tax_profile_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('tax_profile_id');
            $table->uuid('tax_id');
            $table->string('treatment', 20);
            $table->timestamps();

            $table->foreign(['organization_id', 'tax_profile_id'], 'tax_profile_lines_profile_foreign')
                ->references(['organization_id', 'id'])->on('tax_profiles')->cascadeOnDelete();
            $table->foreign(['organization_id', 'tax_id'], 'tax_profile_lines_tax_foreign')
                ->references(['organization_id', 'id'])->on('tax_taxes')->restrictOnDelete();
            $table->unique(['organization_id', 'tax_profile_id', 'tax_id'], 'tax_profile_lines_profile_tax_unique');
            $table->unique(['organization_id', 'id'], 'tax_profile_lines_organization_id_id_unique');
            $table->index(['organization_id', 'tax_id'], 'tax_profile_lines_tax_index');
        });

        Schema::create('tax_profile_line_brackets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organization_organizations')->restrictOnDelete();
            $table->uuid('tax_profile_line_id');
            $table->uuid('tax_bracket_id');
            $table->decimal('value', 24, 6);
            $table->timestamps();

            $table->foreign(['organization_id', 'tax_profile_line_id'], 'tax_profile_line_brackets_line_foreign')
                ->references(['organization_id', 'id'])->on('tax_profile_lines')->cascadeOnDelete();
            $table->foreign(['organization_id', 'tax_bracket_id'], 'tax_profile_line_brackets_bracket_foreign')
                ->references(['organization_id', 'id'])->on('tax_brackets')->restrictOnDelete();
            $table->unique(['organization_id', 'tax_profile_line_id', 'tax_bracket_id'], 'tax_profile_line_brackets_line_bracket_unique');
            $table->index(['organization_id', 'tax_bracket_id'], 'tax_profile_line_brackets_bracket_index');
        });

        DB::statement('ALTER TABLE tax_profile_line_brackets ADD CONSTRAINT tax_profile_line_brackets_value_check CHECK (value >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_profile_line_brackets');
        Schema::dropIfExists('tax_profile_lines');
        Schema::dropIfExists('tax_profiles');
        Schema::dropIfExists('tax_dependencies');
        Schema::dropIfExists('tax_brackets');
        Schema::dropIfExists('tax_taxes');
    }
};
