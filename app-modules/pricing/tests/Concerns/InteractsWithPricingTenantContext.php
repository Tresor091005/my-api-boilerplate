<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait InteractsWithPricingTenantContext
{
    protected function initializePricingTenantContext(): void
    {
        $this->organizationId = Str::uuid7()->toString();
        $this->otherOrganizationId = Str::uuid7()->toString();

        $now = now();
        DB::table('organization_organizations')->insert([
            [
                'id'         => $this->organizationId,
                'name'       => 'Pricing Test Organization',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'id'         => $this->otherOrganizationId,
                'name'       => 'Pricing Other Organization',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);

        setPermissionsTeamId($this->organizationId);
    }
}
