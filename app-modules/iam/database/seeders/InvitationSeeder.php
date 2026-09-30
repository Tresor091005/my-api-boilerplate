<?php

declare(strict_types=1);

namespace Lahatre\Iam\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Iam\Models\Invitation;
use Lahatre\Iam\Models\Role;
use Lahatre\Organization\Models\Organization;

class InvitationSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->first();
        if (!$organization) {
            return;
        }
        $role = Role::query()->where('team_id', $organization->id)
            ->where('is_builtin', false)->where('guard_name', config('auth.defaults.guard'))->first();
        if (!$role) {
            return;
        }
        $invitation = Invitation::query()->firstOrCreate([
            'organization_id' => $organization->id,
            'email'           => 'invited@example.com',
        ]);
        $invitation->roles()->syncWithPivotValues([$role->id], ['organization_id' => $organization->id]);
    }
}
