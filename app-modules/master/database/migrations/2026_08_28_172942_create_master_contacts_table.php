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
        Schema::create('master_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->index()->constrained('organization_organizations')->restrictOnDelete();
            $table->string('contactable_type');
            $table->uuid('contactable_id');
            $table->string('type', 50);
            $table->string('value', 2048);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE INDEX master_contacts_contactable_index ON master_contacts (organization_id, contactable_type, contactable_id)');
        DB::statement('CREATE UNIQUE INDEX master_contacts_one_primary_index ON master_contacts (organization_id, contactable_type, contactable_id) WHERE is_primary = true AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('master_contacts');
    }
};
