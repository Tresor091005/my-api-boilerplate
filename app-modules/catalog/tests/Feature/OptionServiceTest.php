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
        'options.list',
        'options.retrieve',
        'options.create',
        'options.update',
        'options.delete',
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

it('manages options through the api resource flow', function (): void {
    $otherOrg = Organization::factory()->create();

    $option = Option::factory()->create([
        'organization_id' => $this->organization->id,
        'name'            => 'Color',
    ]);

    $otherOption = Option::factory()->create([
        'organization_id' => $otherOrg->id,
        'name'            => 'Other Color',
    ]);

    OptionValue::factory()->create([
        'organization_id' => $this->organization->id,
        'option_id'       => $option->id,
        'value'           => 'Blue',
    ]);

    $this->getJson('/v1/catalog/options')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Color')
        ->assertJsonMissing(['name' => 'other color'])
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']]);

    $this->getJson("/v1/catalog/options/{$option->id}")
        ->assertOk()
        ->assertJsonPath('name', 'Color');

    $createdResponse = $this->postJson('/v1/catalog/options', [
        'name'   => 'Size',
        'values' => ['Large', 'SMALL'],
    ]);

    $createdResponse
        ->assertCreated()
        ->assertJsonPath('name', 'size')
        ->assertJsonPath('values.0.value', 'large')
        ->assertJsonPath('values.1.value', 'small');

    $createdOptionId = (string) $createdResponse->json('id');

    $this->putJson("/v1/catalog/options/{$createdOptionId}", [
        'name'   => 'Material',
        'values' => ['Cotton'],
    ])->assertOk()
        ->assertJsonPath('name', 'material')
        ->assertJsonPath('values.2.value', 'cotton');

    $this->deleteJson("/v1/catalog/options/{$createdOptionId}")
        ->assertNoContent();

    expect(Option::query()->whereKey($createdOptionId)->exists())->toBeFalse()
        ->and(OptionValue::query()->where('option_id', $createdOptionId)->exists())->toBeFalse();
});

it('prevents deleting an option that is currently used by a variant option value', function (): void {
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

    $this->deleteJson("/v1/catalog/options/{$option->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', __('catalog::exceptions.option_in_use'))
        ->assertJsonPath('errors.type', 'OptionInUseException');

    expect(Option::query()->whereKey($option->id)->exists())->toBeTrue();
});
