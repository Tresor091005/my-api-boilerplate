<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->organization = Organization::factory()->create([
        'name' => 'Integration Test Org',
    ]);

    setPermissionsTeamId($this->organization->id);

    $this->user = User::factory()->create();
    $this->member = OrganizationMember::create([
        'user_id'         => $this->user->id,
        'organization_id' => $this->organization->id,
    ]);

    $this->role = Role::query()->firstOrCreate([
        'name'       => 'integration-admin',
        'guard_name' => 'sanctum',
    ]);

    $this->memberRole = MemberRole::create([
        'organization_id' => $this->organization->id,
        'member_id'       => $this->member->id,
        'role_id'         => $this->role->id,
    ]);

    $permissions = [
        'categories.list', 'categories.retrieve', 'categories.create', 'categories.update', 'categories.delete',
        'options.list', 'options.retrieve', 'options.create', 'options.update', 'options.delete',
        'option_values.list', 'option_values.retrieve', 'option_values.create', 'option_values.update', 'option_values.delete',
        'products.list', 'products.retrieve', 'products.create', 'products.update', 'products.delete',
        'product_variants.list', 'product_variants.retrieve', 'product_variants.create', 'product_variants.update', 'product_variants.delete',
    ];

    collect($permissions)->each(function (string $permissionName): void {
        Permission::query()->firstOrCreate([
            'name'       => $permissionName,
            'guard_name' => 'sanctum',
        ]);
    });
    $this->memberRole->givePermissionTo($permissions);

    $token = $this->user->createToken('integration-token');
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

it('enforces catalog permissions at http layer', function (): void {
    $category = Category::factory()->create(['organization_id' => $this->organization->id]);
    $option = Option::factory()->create(['organization_id' => $this->organization->id]);
    $optionValue = OptionValue::factory()->create([
        'organization_id' => $this->organization->id,
        'option_id'       => $option->id,
    ]);
    $product = Product::factory()->create(['organization_id' => $this->organization->id]);
    $variant = ProductVariant::factory()->create([
        'organization_id' => $this->organization->id,
        'product_id'      => $product->id,
    ]);

    $this->memberRole->revokePermissionTo(Permission::query()->pluck('name')->all());

    $this->getJson('/v1/catalog/categories')->assertForbidden();
    $this->postJson('/v1/catalog/categories', ['name' => 'x'])->assertForbidden();
    $this->getJson("/v1/catalog/options/{$option->id}/values")->assertForbidden();
    $this->postJson('/v1/catalog/products', ['name' => 'x'])->assertForbidden();
    $this->getJson("/v1/catalog/products/{$product->id}/variants")->assertForbidden();
    $this->patchJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}", ['sku' => 'ANY'])->assertForbidden();
    $this->deleteJson("/v1/catalog/options/{$option->id}/values/{$optionValue->id}")->assertForbidden();
    $this->deleteJson("/v1/catalog/categories/{$category->id}")->assertForbidden();
});
