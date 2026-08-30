<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Customer\Models\Customer;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Master\Models\Address;
use Lahatre\Master\Models\Contact;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create();
    setPermissionsTeamId($this->organization->id);
    $this->user = User::factory()->create();
    $this->member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->role = Role::query()->firstOrCreate([
        'name'       => 'customer-relations-admin',
        'guard_name' => 'sanctum',
    ]);
    $this->memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'customer_customer.update',
        'master_address.create', 'master_address.update', 'master_address.delete',
        'master_contact.create', 'master_contact.update', 'master_contact.delete',
    ];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $this->memberRole->givePermissionTo($permissions);

    $token = $this->user->createToken('customer-relations-token');
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

it('manages customer addresses through nested bulk and single endpoints', function (): void {
    $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $created = $this->postJson("/v1/customer/customers/{$customer->id}/addresses", [
        'addresses' => [[
            'line'       => 'Rue 123',
            'city'       => 'Cotonou',
            'country'    => 'Benin',
            'is_primary' => true,
        ], [
            'line'    => 'Rue 456',
            'city'    => 'Porto-Novo',
            'country' => 'Benin',
        ]],
    ])->assertCreated()->assertJsonCount(2, 'data');

    $addressId = $created->json('data.1.id');
    $this->patchJson("/v1/customer/customers/{$customer->id}/addresses/{$addressId}", [
        'city'       => 'Abomey-Calavi',
        'is_primary' => true,
    ])->assertOk()->assertJsonPath('data.city', 'Abomey-Calavi');

    expect(Address::query()->whereKey($addressId)->value('is_primary'))->toBeTrue()
        ->and(Address::query()->where('id', $created->json('data.0.id'))->value('is_primary'))->toBeFalse();

    $this->deleteJson("/v1/customer/customers/{$customer->id}/addresses", [
        'ids' => [$addressId],
    ])->assertNoContent();

    expect(Address::withTrashed()->whereKey($addressId)->value('deleted_at'))->not->toBeNull();
});

it('manages customer contacts through nested bulk and single endpoints', function (): void {
    $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $created = $this->postJson("/v1/customer/customers/{$customer->id}/contacts", [
        'contacts' => [[
            'type'       => 'email',
            'value'      => 'primary@example.test',
            'is_primary' => true,
        ], [
            'type'  => 'phone',
            'value' => '+22900000000',
        ]],
    ])->assertCreated()->assertJsonCount(2, 'data');

    $contactId = $created->json('data.1.id');
    $this->patchJson("/v1/customer/customers/{$customer->id}/contacts/{$contactId}", [
        'value'      => 'updated@example.test',
        'is_primary' => true,
    ])->assertOk()->assertJsonPath('data.value', 'updated@example.test');

    $this->deleteJson("/v1/customer/customers/{$customer->id}/contacts", [
        'ids' => [$contactId],
    ])->assertNoContent();

    expect(Contact::withTrashed()->whereKey($contactId)->value('deleted_at'))->not->toBeNull();
});
