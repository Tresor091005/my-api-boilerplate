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
        $ownerId = (config('auth.providers.users.model'))::factory()->create()->getKey();
        DB::table('organization_organizations')->insert([
            [
                'id'                       => $this->organizationId,
                'name'                     => 'Master Test Organization',
                'owner_id'                 => $ownerId,
                'functional_currency_code' => 'XOF',
                'created_at'               => $now,
                'updated_at'               => $now,
                'deleted_at'               => null,
            ],
            [
                'id'                       => $this->otherOrganizationId,
                'name'                     => 'Master Other Organization',
                'owner_id'                 => $ownerId,
                'functional_currency_code' => 'XOF',
                'created_at'               => $now,
                'updated_at'               => $now,
                'deleted_at'               => null,
            ],
        ]);

        setPermissionsTeamId($this->organizationId);
    }
}
