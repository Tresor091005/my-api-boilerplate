<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Inventory\Models\InventoryItem;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Organization\Models\Organization;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    clearApiBindingTestContext();
    $this->withoutMiddleware(ThrottleRequests::class);
});

afterEach(function (): void {
    clearApiBindingTestContext();
});

function clearApiBindingTestContext(): void
{
    authContext()->clear();
    setPermissionsTeamId(null);
    app('auth')->forgetGuards();
}

/** @return array{organization: Organization, user: User, assignment: MemberRole} */
function authenticateApiBindingTestContext(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $member = OrganizationMember::factory()->create(['organization_id' => $organization->id, 'user_id' => $user->id]);
    $role = Role::factory()->create(['team_id' => $organization->id]);
    $assignment = MemberRole::factory()->create([
        'organization_id' => $organization->id, 'member_id' => $member->id, 'role_id' => $role->id,
    ]);
    setPermissionsTeamId($organization->id);
    $permissions = array_map(fn (string $name): Permission => Permission::factory()->create(['name' => $name]), [
        'catalog_option.retrieve', 'catalog_option.update', 'catalog_product.retrieve', 'catalog_product.update_variant', 'master_unit.list',
    ]);
    $assignment->givePermissionTo($permissions);
    $token = $user->createToken('request-context');
    $token->accessToken->update(['metadata' => [
        'organization_id' => $organization->id, 'member_id' => $member->id,
        'member_role_id'  => $assignment->id, 'role_id' => $role->id,
    ]]);
    currentTestCase()->withToken($token->plainTextToken);
    clearApiBindingTestContext();

    return compact('organization', 'user', 'assignment');
}

it('resolves the token organization before binding option values for every fresh request', function (): void {
    $context = authenticateApiBindingTestContext();
    $option = Option::factory()->create(['organization_id' => $context['organization']->id]);
    $value = OptionValue::factory()->create(['organization_id' => $context['organization']->id, 'option_id' => $option->id]);
    $url = "/v1/catalog/options/{$option->id}/values/{$value->id}";
    $this->getJson($url)->assertOk()->assertJsonPath('data.id', $value->id);
    clearApiBindingTestContext();
    $this->putJson($url, ['value' => 'updated'])->assertNoContent();
    expect($value->fresh()->value)->toBe('updated');
    clearApiBindingTestContext();
    $this->deleteJson($url)->assertNoContent();
    expect($value->fresh()->trashed())->toBeTrue();
});

it('resolves the token organization before binding product variants for every fresh request', function (): void {
    $context = authenticateApiBindingTestContext();
    $product = Product::factory()->create(['organization_id' => $context['organization']->id]);
    $item = CatalogItem::factory()->create(['organization_id' => $context['organization']->id]);
    $unit = Unit::factory()->create([
        'organization_id' => $context['organization']->id,
        'group_id'        => $item->unit_group_id,
        'ratio'           => 1,
    ]);
    InventoryItem::factory()->create([
        'organization_id' => $context['organization']->id,
        'itemable_type'   => $item->getMorphClass(),
        'itemable_id'     => $item->id,
        'sku'             => $item->sku,
        'base_unit_code'  => $unit->code,
    ]);
    $variant = ProductVariant::factory()->forCatalogItem($item)->create(['product_id' => $product->id]);
    $url = "/v1/catalog/products/{$product->id}/variants/{$variant->id}";
    $this->getJson($url)->assertOk()->assertJsonPath('data.id', $variant->id);
    clearApiBindingTestContext();
    $this->putJson($url, ['sku' => 'CONTEXT-UPDATED'])->assertNoContent();
    expect($item->fresh()->sku)->toBe('CONTEXT-UPDATED');
});

it('keeps nested children scoped to their parent after resolving a fresh organization context', function (): void {
    $context = authenticateApiBindingTestContext();
    $option = Option::factory()->create(['organization_id' => $context['organization']->id]);
    $otherOption = Option::factory()->create(['organization_id' => $context['organization']->id]);
    $value = OptionValue::factory()->create(['organization_id' => $context['organization']->id, 'option_id' => $otherOption->id]);
    $this->getJson("/v1/catalog/options/{$option->id}/values/{$value->id}")->assertNotFound();
    clearApiBindingTestContext();
    $product = Product::factory()->create(['organization_id' => $context['organization']->id]);
    $otherProduct = Product::factory()->create(['organization_id' => $context['organization']->id]);
    $item = CatalogItem::factory()->create(['organization_id' => $context['organization']->id]);
    $variant = ProductVariant::factory()->forCatalogItem($item)->create(['product_id' => $otherProduct->id]);
    $this->getJson("/v1/catalog/products/{$product->id}/variants/{$variant->id}")->assertNotFound();
});

it('rejects a token without a selected organization before tenant scoped binding', function (): void {
    $context = authenticateApiBindingTestContext();
    $token = $context['user']->createToken('no-organization');
    $option = Option::factory()->create(['organization_id' => $context['organization']->id]);
    $value = OptionValue::factory()->create(['organization_id' => $context['organization']->id, 'option_id' => $option->id]);
    $this->withToken($token->plainTextToken)->getJson("/v1/catalog/options/{$option->id}/values/{$value->id}")->assertForbidden();
});

it('loads unit groups only when requested and keeps other organization units out for both sort paths', function (string $sortBy): void {
    $context = authenticateApiBindingTestContext();
    $group = UnitGroup::factory()->create(['organization_id' => $context['organization']->id]);
    $unit = Unit::factory()->create(['organization_id' => $context['organization']->id, 'group_id' => $group->id, 'name' => 'Context regression unit']);
    $foreignGroup = UnitGroup::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    Unit::factory()->create(['organization_id' => $foreignGroup->organization_id, 'group_id' => $foreignGroup->id, 'name' => 'Context regression foreign']);
    if ($sortBy === 'group') {
        $deletedGroup = UnitGroup::factory()->create(['organization_id' => $context['organization']->id]);
        Unit::factory()->create(['organization_id' => $context['organization']->id, 'group_id' => $deletedGroup->id, 'name' => 'Context regression deleted group']);
        $deletedGroup->delete();
    }
    $url = '/v1/master/units?name=Context%20regression&sort_by='.$sortBy;
    $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unit->id)->assertJsonMissingPath('data.0.group');
    clearApiBindingTestContext();
    $this->getJson($url.'&include=group')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.group.id', $group->id);
})->with(['name', 'group']);
