<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Models\ServiceDeliverableTemplate;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Service API Organization']);
    setPermissionsTeamId($this->organization->id);

    $user = User::factory()->create();
    $member = OrganizationMember::create([
        'user_id'         => $user->id,
        'organization_id' => $this->organization->id,
    ]);
    $role = Role::query()->firstOrCreate([
        'name'       => 'service-api-admin',
        'guard_name' => 'sanctum',
    ]);
    $this->memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);

    $permissions = [
        'catalog_service.list',
        'catalog_service.retrieve',
        'catalog_service.create',
        'catalog_service.update',
        'catalog_service.delete',
    ];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $this->memberRole->givePermissionTo($permissions);

    $token = $user->createToken('service-api-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $this->memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);

    $this->unitGroup = UnitGroup::factory()->create(['organization_id' => null]);
});

it('exposes service CRUD with templates embedded as an ordered array', function (): void {
    $created = $this->postJson('/v1/catalog/services?response=resource&include=deliverable_templates', [
        'name'                  => 'Annual Maintenance',
        'sku'                   => 'service-maintenance',
        'unit_group_id'         => $this->unitGroup->id,
        'is_active'             => true,
        'deliverable_templates' => [
            ['name' => 'Inspection report'],
            ['name' => 'Maintenance certificate'],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.handle', 'annual-maintenance')
        ->assertJsonPath('data.sku', 'SERVICE-MAINTENANCE')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonCount(2, 'data.deliverable_templates')
        ->assertJsonPath('data.deliverable_templates.0.position', 1)
        ->assertJsonPath('data.deliverable_templates.1.position', 2);

    $serviceId = (string) $created->json('data.id');
    $firstTemplateId = (string) $created->json('data.deliverable_templates.0.id');
    $secondTemplateId = (string) $created->json('data.deliverable_templates.1.id');

    expect(CatalogItem::query()->findOrFail($serviceId)->is_stockable)->toBeFalse();

    $this->getJson('/v1/catalog/services')
        ->assertOk()
        ->assertJsonFragment(['id' => $serviceId])
        ->assertJsonMissingPath('data.0.deliverable_templates');

    $this->getJson("/v1/catalog/services/{$serviceId}?include=deliverable_templates")
        ->assertOk()
        ->assertJsonCount(2, 'data.deliverable_templates');

    $updated = $this->patchJson("/v1/catalog/services/{$serviceId}?response=resource&include=deliverable_templates", [
        'name'                  => 'Premium Maintenance',
        'is_active'             => false,
        'deliverable_templates' => [
            ['id' => $secondTemplateId, 'name' => 'Signed certificate'],
            ['name' => 'Customer recommendations'],
        ],
    ])->assertOk()
        ->assertJsonPath('data.name', 'Premium Maintenance')
        ->assertJsonPath('data.handle', 'annual-maintenance')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.deliverable_templates.0.id', $secondTemplateId)
        ->assertJsonPath('data.deliverable_templates.0.position', 1)
        ->assertJsonPath('data.deliverable_templates.1.position', 2);

    expect((string) $updated->json('data.deliverable_templates.1.id'))->not->toBe($firstTemplateId)
        ->and(ServiceDeliverableTemplate::withTrashed()->whereKey($firstTemplateId)->exists())->toBeFalse();

    $this->patchJson("/v1/catalog/services/{$serviceId}", [
        'unit_group_id' => $this->unitGroup->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('unit_group_id');

    $this->deleteJson("/v1/catalog/services/{$serviceId}")->assertNoContent();
    $this->getJson("/v1/catalog/services/{$serviceId}")->assertNotFound();

    expect(Service::withTrashed()->whereKey($serviceId)->exists())->toBeTrue()
        ->and(ServiceDeliverableTemplate::query()->where('service_id', $serviceId)->count())->toBe(0)
        ->and(ServiceDeliverableTemplate::withTrashed()->where('service_id', $serviceId)->count())->toBe(2)
        ->and(ServiceDeliverableTemplate::withTrashed()
            ->where('service_id', $serviceId)
            ->whereNull('deleted_at')
            ->count())->toBe(0);
});

it('requires at least one template and enforces service permissions', function (): void {
    $this->postJson('/v1/catalog/services', [
        'name'                  => 'Incomplete service',
        'unit_group_id'         => $this->unitGroup->id,
        'deliverable_templates' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('deliverable_templates');

    $this->memberRole->revokePermissionTo('catalog_service.list');

    $this->getJson('/v1/catalog/services')->assertForbidden();
});
