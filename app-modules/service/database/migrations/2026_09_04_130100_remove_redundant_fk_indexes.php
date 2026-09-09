<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS service_commitments_service_fk_index');
        DB::statement('DROP INDEX IF EXISTS service_deliverables_commitment_fk_index');
        DB::statement('DROP INDEX IF EXISTS service_evidences_deliverable_fk_index');
    }

    public function down(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS service_commitments_service_fk_index ON service_commitments (service_id, organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS service_deliverables_commitment_fk_index ON service_deliverables (commitment_id, organization_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS service_evidences_deliverable_fk_index ON service_evidences (deliverable_id, organization_id)');
    }
};
