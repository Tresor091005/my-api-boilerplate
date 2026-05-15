<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait InteractsWithCatalogTenantContext
{
    protected string $organizationId;

    protected string $otherOrganizationId;

    protected function initializeCatalogTenantContext(): void
    {
        $this->organizationId = Str::uuid7()->toString();
        $this->otherOrganizationId = Str::uuid7()->toString();

        $now = now();
        DB::table('organization_organizations')->insert([
            [
                'id'         => $this->organizationId,
                'name'       => 'Catalog Test Organization',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'id'         => $this->otherOrganizationId,
                'name'       => 'Catalog Other Organization',
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ]);

        setPermissionsTeamId($this->organizationId);
    }
}
