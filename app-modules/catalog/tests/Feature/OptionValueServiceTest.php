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
        'option_values.list',
        'option_values.retrieve',
        'option_values.create',
        'option_values.update',
        'option_values.delete',
    ])->each(function (string $permissionName): void {
        Permission::query()->firstOrCreate([
            'name'       => $permissionName,
            'guard_name' => 'sanctum',
        ]);
    });

    $user->givePermissionTo([
        'option_values.list',
        'option_values.retrieve',
        'option_values.create',
        'option_values.update',
        'option_values.delete',
    ]);

    Sanctum::actingAs($user);
});

it('manages option values through the api resource flow', function (): void {
    $option = Option::factory()->create(['name' => 'Color']);
    $optionValue = OptionValue::factory()->create([
        'option_id' => $option->id,
        'value'     => 'Blue',
    ]);

    $this->getJson("/v1/catalog/options/{$option->id}/values")
        ->assertOk()
        ->assertJsonPath('data.0.id', $optionValue->id)
        ->assertJsonPath('data.0.option_id', $option->id)
        ->assertJsonStructure(['meta' => ['per_page', 'next_cursor', 'prev_cursor']]);

    $this->getJson("/v1/catalog/options/{$option->id}/values/{$optionValue->id}")
        ->assertOk()
        ->assertJsonPath('value', 'Blue')
        ->assertJsonPath('option_id', $option->id);

    $createdResponse = $this->postJson("/v1/catalog/options/{$option->id}/values", [
        'values' => ['Red', 'GREEN'],
    ]);

    $createdResponse
        ->assertCreated()
        ->assertJsonCount(2)
        ->assertJsonPath('0.option_id', $option->id);

    expect(collect($createdResponse->json())->pluck('value')->sort()->values()->all())
        ->toBe(['green', 'red']);

    $createdOptionValueId = (string) $createdResponse->json('0.id');

    $this->putJson("/v1/catalog/options/{$option->id}/values/{$createdOptionValueId}", [
        'value' => 'Yellow',
    ])->assertOk()
        ->assertJsonPath('value', 'yellow')
        ->assertJsonPath('option_id', $option->id);

    $this->deleteJson("/v1/catalog/options/{$option->id}/values/{$createdOptionValueId}")
        ->assertNoContent();

    expect(OptionValue::query()->whereKey($createdOptionValueId)->exists())->toBeFalse();
});

it('prevents deleting an option value that is currently used by a variant option value', function (): void {
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

    $this->deleteJson("/v1/catalog/options/{$option->id}/values/{$optionValue->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', __('catalog::exceptions.option_value_in_use'))
        ->assertJsonPath('errors.type', 'OptionValueInUseException');

    expect(OptionValue::query()->whereKey($optionValue->id)->exists())->toBeTrue();
});
