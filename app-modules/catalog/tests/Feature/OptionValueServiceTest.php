<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // 1. Setup organization
    $this->organization = Organization::factory()->create([
        'name' => 'Test Org',
    ]);

    setPermissionsTeamId($this->organization->id);

    // 2. Setup user and membership
    $this->user = User::factory()->create();

    $this->member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);

    // 3. Setup Role and Permissions
    $this->role = Role::query()->firstOrCreate([
        'name'       => 'admin',
        'guard_name' => 'sanctum',
    ]);

    $this->memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'option_values.list',
        'option_values.retrieve',
        'option_values.create',
        'option_values.update',
        'option_values.delete',
    ];

    collect($permissions)->each(function (string $permissionName): void {
        Permission::query()->firstOrCreate([
            'name'       => $permissionName,
            'guard_name' => 'sanctum',
        ]);
    });

    $this->memberRole->givePermissionTo($permissions);

    // 4. Create token with metadata
    $token = $this->user->createToken('test_token');
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

it('manages option values through the api resource flow and scopes by organization', function (): void {
    $otherOrg = Organization::factory()->create();

    $option = Option::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Color',
    ]);

    $otherOption = Option::factory()->create([
        'organization_id' => $otherOrg->id,
        'name'            => 'Other Color',
    ]);

    $optionValue = OptionValue::factory()->create([
        'organization_id' => $this->organization->id,
        'option_id'       => $option->id,
        'value'           => 'Blue',
    ]);

    $otherOptionValue = OptionValue::factory()->create([
        'organization_id' => $otherOrg->id,
        'option_id'       => $otherOption->id,
        'value'           => 'Red',
    ]);

    // 1. List - should only see our organization's option values for the given option
    $this->getJson("/v1/catalog/options/{$option->id}/values")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $optionValue->id)
        ->assertJsonMissing(['id' => $otherOptionValue->id]);

    // 2. List - other organization option (Not Found because of service scope)
    $this->getJson("/v1/catalog/options/{$otherOption->id}/values")
        ->assertNotFound();

    // 3. Show - our organization
    $this->getJson("/v1/catalog/options/{$option->id}/values/{$optionValue->id}")
        ->assertOk()
        ->assertJsonPath('value', 'Blue');

    // 4. Show - other organization (Forbidden because of policy)
    $this->getJson("/v1/catalog/options/{$otherOption->id}/values/{$otherOptionValue->id}")
        ->assertForbidden();

    // 5. Create - should automatically assign our organization_id
    $createdResponse = $this->postJson("/v1/catalog/options/{$option->id}/values", [
        'values' => ['Yellow'],
    ]);

    $createdResponse->assertCreated()
        ->assertJsonCount(1)
        ->assertJsonPath('0.value', 'yellow');

    $createdOptionValueId = (string) $createdResponse->json('0.id');
    $createdOptionValue = OptionValue::find($createdOptionValueId);

    expect($createdOptionValue->organization_id)->toBe($this->organization->id);

    // 6. Update - our organization
    $this->putJson("/v1/catalog/options/{$option->id}/values/{$optionValue->id}", [
        'value' => 'Cyan',
    ])->assertOk()
        ->assertJsonPath('value', 'cyan');

    // 7. Update - other organization (Forbidden)
    $this->putJson("/v1/catalog/options/{$otherOption->id}/values/{$otherOptionValue->id}", [
        'value' => 'Hacked',
    ])->assertForbidden();

    // 8. Delete - our organization
    $this->deleteJson("/v1/catalog/options/{$option->id}/values/{$createdOptionValueId}")
        ->assertNoContent();

    expect(OptionValue::whereKey($createdOptionValueId)->exists())->toBeFalse();

    // 9. Delete - other organization (Forbidden)
    $this->deleteJson("/v1/catalog/options/{$otherOption->id}/values/{$otherOptionValue->id}")
        ->assertForbidden();

    expect(OptionValue::whereKey($otherOptionValue->id)->exists())->toBeTrue();
});

it('prevents deleting an option value that is currently used by a variant option value', function (): void {
    $option = Option::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Color',
    ]);
    $optionValue = OptionValue::factory()->create([
        'organization_id' => $this->organization->id,
        'option_id'       => $option->id,
        'value'           => 'Blue',
    ]);
    $product = Product::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'T-Shirt',
    ]);
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organization->id,
        'product_id'      => $product->id,
    ]);

    VariantOptionValue::factory()->create([
        'product_id'      => $product->id,
        'variant_id'      => $variant->id,
        'option_id'       => $option->id,
        'option_value_id' => $optionValue->id,
    ]);

    $this->deleteJson("/v1/catalog/options/{$option->id}/values/{$optionValue->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', __('catalog::exceptions.option_value_in_use'))
        ->assertJsonPath('errors.type', 'OptionValueInUseException');

    expect(OptionValue::query()->whereKey($optionValue->id)->exists())->toBeTrue();
});
