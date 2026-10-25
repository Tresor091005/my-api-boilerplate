<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Customer\Http\Resources\CustomerResource;
use Lahatre\Customer\Models\Customer;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Library\Models\FileAttachment;
use Lahatre\Master\Models\Address;
use Lahatre\Master\Models\Contact;
use Lahatre\Organization\Models\Organization;
use Lahatre\ResponseSelection\Testing\AssertsBoundedQueries;
use Lahatre\ResponseSelection\Testing\AssertsResponseFieldIsolation;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class, AssertsBoundedQueries::class, AssertsResponseFieldIsolation::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create(['name' => 'Customer Selection Organization']);
    setPermissionsTeamId($this->organization->id);

    $user = User::factory()->create();
    $member = OrganizationMember::create(['user_id' => $user->id, 'organization_id' => $this->organization->id]);
    $role = Role::query()->firstOrCreate(['name' => 'customer-selection-reader', 'guard_name' => 'sanctum']);
    $memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'role_id'         => $role->id,
    ]);

    $permissions = ['customer_customer.list', 'customer_customer.retrieve'];
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
    }
    $memberRole->givePermissionTo($permissions);

    $token = $user->createToken('customer-selection-token');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $this->organization->id,
        'member_id'       => $member->id,
        'member_role_id'  => $memberRole->id,
        'role_id'         => $role->id,
    ]]);
    $this->withToken($token->plainTextToken);
});

/**
 * A customer with an address, a contact and a profile picture, so every include it offers has a row.
 */
function customerSelectionCustomer(): Customer
{
    $customer = Customer::factory()->create(['organization_id' => currentOrganizationId()]);
    $owner = ['organization_id' => currentOrganizationId()];
    Address::factory()->create([...$owner, 'addressable_type' => $customer->getMorphClass(), 'addressable_id' => $customer->id]);
    Contact::factory()->create([...$owner, 'contactable_type' => $customer->getMorphClass(), 'contactable_id' => $customer->id]);
    FileAttachment::factory()->forParent($customer)->create(['slot' => 'profile_picture', 'position' => 1]);

    return $customer;
}

it('renders every field of the customer routes alone as in the full selection', function (): void {
    $customer = customerSelectionCustomer();

    $this->assertFieldsRenderInIsolation('/v1/customer/customers', CustomerResource::class, 1);
    $this->assertFieldsRenderInIsolation("/v1/customer/customers/{$customer->id}", CustomerResource::class, 1);
});

it('runs as many queries for more customers with their includes', function (): void {
    $this->assertBoundedQueries(
        fn (int $count) => collect(range(1, $count))->each(fn (): Customer => customerSelectionCustomer()),
        fn () => $this->getJson('/v1/customer/customers?include=addresses,contacts,profile_picture_files')->assertOk(),
    );
});

it('renders the profile picture with its file values and content route', function (): void {
    $customer = customerSelectionCustomer();
    $attachment = $customer->profilePictureFileAttachments()->with('file')->firstOrFail();

    $this->getJson("/v1/customer/customers/{$customer->id}")->assertOk()->assertJsonMissingPath('data.profile_picture_files');
    $this->getJson("/v1/customer/customers/{$customer->id}?include=profile_picture_files")
        ->assertOk()
        ->assertJsonPath('data.profile_picture_files.0.id', $attachment->id)
        ->assertJsonPath('data.profile_picture_files.0.file_id', $attachment->file_id)
        ->assertJsonPath('data.profile_picture_files.0.name', $attachment->file->name)
        ->assertJsonPath('data.profile_picture_files.0.content_url', "/v1/customer/customers/{$customer->id}/files/{$attachment->id}/content");
});

it('rejects the former profile picture include name', function (): void {
    $customer = customerSelectionCustomer();

    $this->getJson("/v1/customer/customers/{$customer->id}?include=files.profile_picture")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('include');
});
