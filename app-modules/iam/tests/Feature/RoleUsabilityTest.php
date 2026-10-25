<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Iam\Models\Role;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

it('finds in the database exactly the roles it accepts one by one in an organization', function (): void {
    $organization = Organization::factory()->create();
    $roles = [
        'own'                  => Role::factory()->create(['team_id' => $organization->id]),
        'built-in'             => Role::factory()->create(['team_id' => null, 'is_builtin' => true]),
        'other organization'   => Role::factory()->create(['team_id' => Organization::factory()->create()->id]),
        'global, not built-in' => Role::factory()->create(['team_id' => null]),
        'other guard'          => Role::factory()->create(['team_id' => $organization->id, 'guard_name' => 'web']),
    ];

    $accepted = array_keys(array_filter($roles, fn (Role $role): bool => $role->isUsableIn($organization->id)));
    $found = Role::query()->usableIn($organization->id)->pluck('id')->all();

    expect($accepted)->toBe(['own', 'built-in'])
        ->and($found)->toEqualCanonicalizing([$roles['own']->id, $roles['built-in']->id]);
});
