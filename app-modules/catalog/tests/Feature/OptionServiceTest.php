<?php

declare(strict_types=1);

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Models\OptionValue;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Catalog\Models\VariantOptionValue;
use Lahatre\Iam\Models\Permission;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId(getDefaultTeamId());

    $user = User::factory()->create();

    collect([
        'options.list',
        'options.retrieve',
        'options.create',
        'options.update',
        'options.delete',
    ])->each(function (string $permissionName): void {
        Permission::query()->firstOrCreate([
            'name'       => $permissionName,
            'guard_name' => 'sanctum',
        ]);
    });

    $user->givePermissionTo([
        'options.list',
        'options.retrieve',
        'options.create',
        'options.update',
        'options.delete',
    ]);

    Sanctum::actingAs($user);
});

it('manages options through the api resource flow', function (): void {
    $option = Option::factory()->create(['name' => 'Color']);
    OptionValue::factory()->create([
        'option_id' => $option->id,
        'value'     => 'Blue',
    ]);

    $this->getJson('/v1/catalog/options')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Color')
        ->assertJsonPath('data.0.values.0.value', 'Blue')
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
    $option = Option::factory()->create(['name' => 'Color']);
    $optionValue = OptionValue::factory()->create([
        'option_id' => $option->id,
        'value'     => 'Blue',
    ]);
    $product = Product::factory()->create(['name' => 'T-Shirt']);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
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
