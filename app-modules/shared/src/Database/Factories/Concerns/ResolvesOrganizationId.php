<?php

declare(strict_types=1);

namespace Lahatre\Shared\Database\Factories\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ResolvesOrganizationId
{
    protected function resolveOrganizationId(): string
    {
        $organizationId = getPermissionsTeamId();

        if (is_string($organizationId) && $organizationId !== '') {
            return $organizationId;
        }

        $organizationId = (string) Str::uuid7();

        DB::table('organization_organizations')->insert([
            'id'         => $organizationId,
            'name'       => 'Factory Organization '.$organizationId,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        return $organizationId;
    }
}
