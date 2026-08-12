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
        Schema::create('pricing_priceable_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->text('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX pricing_priceable_groups_organization_id_index ON pricing_priceable_groups (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX pricing_priceable_groups_organization_id_name_unique ON pricing_priceable_groups (organization_id, name) WHERE deleted_at IS NULL');

        Schema::create('pricing_party_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->text('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX pricing_party_groups_organization_id_index ON pricing_party_groups (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX pricing_party_groups_organization_id_name_unique ON pricing_party_groups (organization_id, name) WHERE deleted_at IS NULL');

        Schema::create('pricing_price_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->string('priceable_type');
            $table->uuid('priceable_id');
            $table->enum('priceable_kind', ['item', 'group']);
            $table->string('party_type')->nullable();
            $table->uuid('party_id')->nullable();
            $table->enum('party_kind', ['actor', 'group'])->nullable();
            $table->string('context');
            $table->bigInteger('unit_price');
            $table->string('currency_code', 3);
            $table->string('unit_code');
            $table->bigInteger('min_quantity')->default(0);
            $table->bigInteger('max_quantity')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency_code')
                ->references('code')
                ->on('master_currencies')
                ->onDelete('restrict');
            $table->foreign('unit_code')
                ->references('code')
                ->on('master_units')
                ->onDelete('restrict');
        });

        DB::statement('ALTER TABLE pricing_price_entries ADD CONSTRAINT pricing_pe_min_qty_non_negative CHECK (min_quantity >= 0)');
        DB::statement('ALTER TABLE pricing_price_entries ADD CONSTRAINT pricing_pe_max_qty_valid CHECK (max_quantity IS NULL OR max_quantity >= min_quantity)');
        DB::statement('ALTER TABLE pricing_price_entries ADD CONSTRAINT pricing_pe_period_valid CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at)');
        DB::statement('ALTER TABLE pricing_price_entries ADD CONSTRAINT pricing_pe_party_scope_nullable CHECK (
            (party_type IS NULL AND party_id IS NULL AND party_kind IS NULL)
            OR
            (party_type IS NOT NULL AND party_id IS NOT NULL AND party_kind IS NOT NULL)
        )');

        DB::statement('CREATE INDEX pricing_price_entries_organization_id_index ON pricing_price_entries (organization_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX pricing_pe_org_ctx_currency_active_idx ON pricing_price_entries (organization_id, context, currency_code, is_active) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX pricing_pe_priceable_scope_idx ON pricing_price_entries (priceable_type, priceable_id, priceable_kind) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX pricing_price_entries_party_type_party_id_party_kind_index ON pricing_price_entries (party_type, party_id, party_kind) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX pricing_price_entries_currency_code_index ON pricing_price_entries (currency_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX pricing_price_entries_unit_code_index ON pricing_price_entries (unit_code) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX pricing_price_entries_starts_at_ends_at_index ON pricing_price_entries (starts_at, ends_at) WHERE deleted_at IS NULL');

        Schema::create('pricing_priceable_group_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->index()
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->foreignUuid('group_id')
                ->index()
                ->constrained('pricing_priceable_groups')
                ->onDelete('cascade');
            $table->string('priceable_type');
            $table->uuid('priceable_id');
            $table->timestamps();
        });

        $table = 'pricing_priceable_group_members';
        DB::statement("CREATE UNIQUE INDEX pricing_pgm_org_group_target_unique ON {$table} (organization_id, group_id, priceable_type, priceable_id)");
        DB::statement("CREATE INDEX pricing_pgm_priceable_lookup_idx ON {$table} (priceable_type, priceable_id)");

        Schema::create('pricing_party_group_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->index()
                ->constrained('organization_organizations')
                ->onDelete('restrict');
            $table->foreignUuid('group_id')
                ->index()
                ->constrained('pricing_party_groups')
                ->onDelete('cascade');
            $table->string('party_type');
            $table->uuid('party_id');
            $table->timestamps();
        });

        $table = 'pricing_party_group_members';
        DB::statement("CREATE UNIQUE INDEX pricing_pagm_org_group_target_unique ON {$table} (organization_id, group_id, party_type, party_id)");
        DB::statement("CREATE INDEX pricing_party_group_members_party_type_party_id_index ON {$table} (party_type, party_id)");
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_party_group_members');
        Schema::dropIfExists('pricing_priceable_group_members');
        Schema::dropIfExists('pricing_price_entries');
        Schema::dropIfExists('pricing_party_groups');
        Schema::dropIfExists('pricing_priceable_groups');
    }
};
