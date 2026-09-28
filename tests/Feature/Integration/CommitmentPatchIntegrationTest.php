<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Lahatre\Catalog\Data\ServiceData;
use Lahatre\Catalog\Services\ServiceService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Commitment\Data\CommitmentData;
use Lahatre\Commitment\Data\DeliverableData;
use Lahatre\Commitment\Services\CommitmentService;
use Lahatre\Commitment\Services\GuestReviewService;
use Lahatre\Customer\Models\Customer;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Shared\Data\MissingValue;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->initializeCatalogTenantContext();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create();
    $member = OrganizationMember::query()->create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organizationId,
    ]);
    $role = Role::query()->firstOrCreate(['name' => 'commitment-editor', 'guard_name' => 'sanctum']);
    $memberRole = MemberRole::query()->create([
        'organization_id' => $this->organizationId,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);

    $group = UnitGroup::factory()->create(['organization_id' => null]);
    $this->catalogService = app(ServiceService::class)->create(ServiceData::fromArray([
        'name'                  => 'Home tutoring',
        'unit_group_id'         => $group->id,
        'is_active'             => true,
        'deliverable_templates' => [['name' => 'Lesson']],
    ]));
    $this->customer = Customer::factory()->create(['organization_id' => $this->organizationId]);
    $this->commitments = app(CommitmentService::class);
    $this->commitment = $this->commitments->create(CommitmentData::fromArray([
        'service_id'   => $this->catalogService->id,
        'customer_id'  => $this->customer->id,
        'client_email' => 'parent@example.test',
        'terms'        => 'Original conditions',
    ]), $this->user->id);
    $this->unit = $this->commitments->addDeliverable($this->commitment, DeliverableData::fromArray([
        'title'             => 'First lesson',
        'description'       => 'Fractions',
        'quantity'          => '2.5000',
        'display_unit_code' => 'hour',
    ]), $this->user->id);

    foreach (['update', 'retrieve'] as $ability) {
        $permission = $this->commitment->getMorphClass().'.'.$ability;
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        $memberRole->givePermissionTo($permission);
    }
    $token = $this->user->createToken('commitment-test');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organizationId,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);
});

it('preserves partial updates and quantity-unit consistency', function (): void {
    $path = "/v1/commitment/service-commitments/{$this->commitment->id}/deliverables/{$this->unit->id}?response=resource";
    $this->patchJson($path, ['title' => 'Updated lesson'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated lesson')
        ->assertJsonPath('data.description', 'Fractions')
        ->assertJsonPath('data.quantity', '2.5000')
        ->assertJsonPath('data.display_unit_code', 'hour');

    $this->patchJson($path, ['description' => null])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated lesson')
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.quantity', '2.5000');

    $this->patchJson($path, ['display_unit_code' => null])->assertStatus(422);
    $this->patchJson($path, ['quantity' => null])->assertStatus(422);
    $this->patchJson($path, ['quantity' => null, 'display_unit_code' => null])
        ->assertOk()
        ->assertJsonPath('data.quantity', null)
        ->assertJsonPath('data.display_unit_code', null);
    $this->postJson("/v1/commitment/service-commitments/{$this->commitment->id}/deliverables", [
        'title' => 'Incomplete lesson', 'display_unit_code' => 'hour',
    ])->assertJsonValidationErrors('quantity');
});

it('distinguishes omitted proposal terms from explicit null', function (): void {
    $commitmentPath = "/v1/commitment/service-commitments/{$this->commitment->id}?response=resource";
    $this->patchJson($commitmentPath, ['title' => 'Updated proposal'])
        ->assertOk()
        ->assertJsonPath('data.terms', 'Original conditions');
    $this->patchJson($commitmentPath, ['title' => 'Updated proposal', 'terms' => null])
        ->assertOk()
        ->assertJsonPath('data.terms', null);

    $this->patchJson($commitmentPath, ['title' => 'Final proposal', 'terms' => 'Accepted terms'])->assertOk();
    $submitted = $this->commitments->submitProposal($this->commitment, null, MissingValue::Instance, $this->user->id);
    app(GuestReviewService::class)->reviewProposal($this->commitment, $submitted->id, 'accept', null);
    $proposalsPath = "/v1/commitment/service-commitments/{$this->commitment->id}/proposals?response=resource";
    $this->postJson($proposalsPath, [])->assertCreated()->assertJsonPath('data.terms', 'Accepted terms');
    $this->postJson($proposalsPath, [
        'terms' => null,
    ])->assertCreated()->assertJsonPath('data.terms', null);
});

it('requires evidence list deliverables to belong to their commitment', function (): void {
    $path = "/v1/commitment/service-commitments/{$this->commitment->id}/deliverables";
    $this->getJson("{$path}/{$this->unit->id}/evidence")
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("{$path}/".Str::uuid().'/evidence')->assertNotFound();

    $another = $this->commitments->create(CommitmentData::fromArray([
        'service_id'   => $this->catalogService->id,
        'customer_id'  => $this->customer->id,
        'client_email' => 'another@example.test',
    ]), $this->user->id);
    $otherUnit = $this->commitments->addDeliverable($another, DeliverableData::fromArray(['title' => 'Other lesson']), $this->user->id);
    $this->getJson("{$path}/{$otherUnit->id}/evidence")->assertNotFound();
});
