<?php

declare(strict_types=1);

namespace Lahatre\Customer\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait InteractsWithCustomerTenantContext
{
    protected function initializeCustomerTenantContext(): void
    {
        $this->organizationId = Str::uuid7()->toString();
        $this->otherOrganizationId = Str::uuid7()->toString();
        $now = now();

        DB::table('organization_organizations')->insert([
            [
                'id'                       => $this->organizationId,
                'name'                     => 'Customer Test Organization',
                'functional_currency_code' => 'XOF',
                'created_at'               => $now,
                'updated_at'               => $now,
                'deleted_at'               => null,
            ],
            [
                'id'                       => $this->otherOrganizationId,
                'name'                     => 'Customer Other Organization',
                'functional_currency_code' => 'XOF',
                'created_at'               => $now,
                'updated_at'               => $now,
                'deleted_at'               => null,
            ],
        ]);

        setPermissionsTeamId($this->organizationId);
    }
}
