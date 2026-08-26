<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait InteractsWithMasterTenantContext
{
    protected function initializeMasterTenantContext(): void
    {
        $this->organizationId = Str::uuid7()->toString();
        $this->otherOrganizationId = Str::uuid7()->toString();

        $now = now();
        DB::table('organization_organizations')->insert([
            [
                'id'                       => $this->organizationId,
                'name'                     => 'Master Test Organization',
                'functional_currency_code' => 'XOF',
                'created_at'               => $now,
                'updated_at'               => $now,
                'deleted_at'               => null,
            ],
            [
                'id'                       => $this->otherOrganizationId,
                'name'                     => 'Master Other Organization',
                'functional_currency_code' => 'XOF',
                'created_at'               => $now,
                'updated_at'               => $now,
                'deleted_at'               => null,
            ],
        ]);

        setPermissionsTeamId($this->organizationId);
    }
}
