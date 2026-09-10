<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Library\Models\Folder;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Folder Test Organization']);
    setPermissionsTeamId($this->organization->id);

    $this->user = User::factory()->create();
    $this->member = OrganizationMember::query()->create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->role = Role::query()->firstOrCreate([
        'name'       => 'folder-endpoints-admin',
        'guard_name' => 'sanctum',
    ]);
    $this->memberRole = MemberRole::query()->create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'library_folder.list',
        'library_folder.retrieve',
        'library_folder.create',
        'library_folder.update',
        'library_folder.delete',
    ];

    foreach ($permissions as $permissionName) {
        Permission::query()->firstOrCreate([
            'name'       => $permissionName,
            'guard_name' => 'sanctum',
        ]);
    }

    $this->memberRole->givePermissionTo($permissions);
    $token = $this->user->createToken('folder-endpoints-token');
    $token->accessToken->update([
        'metadata' => [
            'organization_id' => $this->organization->id,
            'member_id'       => $this->member->id,
            'member_role_id'  => $this->memberRole->id,
            'role_id'         => $this->role->id,
        ],
    ]);
    $this->withToken($token->plainTextToken);
});

it('supports folder CRUD and soft deletes empty folders', function (): void {
    $folderId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Drafts',
    ])->assertCreated()->json('data.id');

    $this->getJson('/v1/library/folders')
        ->assertOk()
        ->assertJsonPath('data.0.id', $folderId);
    $this->getJson("/v1/library/folders/{$folderId}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Drafts');
    $this->patchJson("/v1/library/folders/{$folderId}?response=resource", [
        'name' => 'Published',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Published');

    $this->deleteJson("/v1/library/folders/{$folderId}")->assertNoContent();
    $this->getJson("/v1/library/folders/{$folderId}")->assertNotFound();

    expect(Folder::withTrashed()->whereKey($folderId)->value('deleted_at'))->not->toBeNull();
});

it('allows reusing the name of a soft-deleted folder', function (): void {
    $folderId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Attachments',
    ])->assertCreated()->json('data.id');

    $this->deleteJson("/v1/library/folders/{$folderId}")->assertNoContent();

    $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Attachments',
    ])->assertCreated();
});

it('rejects duplicate names, self-parenting, descendant parenting, and non-empty deletion', function (): void {
    $parentId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Projects',
    ])->assertCreated()->json('data.id');
    $childId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name'      => 'Current',
        'parent_id' => $parentId,
    ])->assertCreated()->json('data.id');

    $this->postJson('/v1/library/folders?response=resource', ['name' => 'Projects'])
        ->assertUnprocessable();
    $this->patchJson("/v1/library/folders/{$parentId}?response=resource", ['parent_id' => $parentId])
        ->assertUnprocessable();
    $this->patchJson("/v1/library/folders/{$parentId}?response=resource", ['parent_id' => $childId])
        ->assertUnprocessable();
    $this->deleteJson("/v1/library/folders/{$parentId}")
        ->assertUnprocessable();
});

it('rejects a soft-deleted folder as a parent', function (): void {
    $folderId = (string) $this->postJson('/v1/library/folders?response=resource', [
        'name' => 'Archived',
    ])->assertCreated()->json('data.id');

    $this->deleteJson("/v1/library/folders/{$folderId}")->assertNoContent();

    $this->postJson('/v1/library/folders?response=resource', [
        'name'      => 'Child',
        'parent_id' => $folderId,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});
