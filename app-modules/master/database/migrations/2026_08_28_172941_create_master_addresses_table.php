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
        Schema::create('master_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->index()->constrained('organization_organizations')->restrictOnDelete();
            $table->string('addressable_type');
            $table->uuid('addressable_id');
            $table->text('line');
            $table->string('city', 100);
            $table->string('country', 100);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX master_addresses_addressable_index ON master_addresses (organization_id, addressable_type, addressable_id)');
        DB::statement('CREATE UNIQUE INDEX master_addresses_one_primary_index ON master_addresses (organization_id, addressable_type, addressable_id) WHERE is_primary = true AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('master_addresses');
    }
};
