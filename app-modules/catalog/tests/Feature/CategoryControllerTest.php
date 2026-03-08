<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Tests\Feature;

use App\Models\User\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Lahatre\Catalog\Models\Category;
use Lahatre\Iam\Models\Permission;
use Tests\TestCase;
use function Pest\Laravel\{postJson, getJson, patchJson, deleteJson, assertDatabaseHas, assertDatabaseMissing, actingAs};

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Disable rate limiter to avoid Redis dependency in tests
    RateLimiter::for('api', fn () => Limit::none());

    // Set team ID for permissions
    setPermissionsTeamId(getDefaultTeamId());

    // Create necessary permissions
    $permissions = [
        'categories.list',
        'categories.retrieve',
        'categories.create',
        'categories.update',
        'categories.delete',
        'product_categories.list',
    ];

    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission, 'guard_name' => 'sanctum']);
    }

    $this->user = User::factory()->create();
    actingAs($this->user);
});

describe('Category Controller', function () {

    describe('POST /v1/catalog/categories', function () {
        it('requires permissions to create a category', function () {
            postJson(route('lahatre.catalog.categories.store'), ['name' => 'Test'])
                ->assertForbidden();
        });

        it('validates required fields', function (array $data, string $errorField) {
            $this->user->givePermissionTo('categories.create');

            postJson(route('lahatre.catalog.categories.store'), $data)
                ->assertJsonValidationErrors($errorField);
        })->with([
            'missing name'      => [['is_active' => true], 'name'],
            'missing is_active' => [['name' => 'Test'], 'is_active'],
        ]);

        it('sanitizes the name before creating', function () {
            $this->user->givePermissionTo('categories.create');

            $response = postJson(route('lahatre.catalog.categories.store'), [
                'name'      => '  Leading and Trailing Spaces  ',
                'is_active' => true,
            ]);

            $response->assertCreated();
            expect($response->json('data.name'))->toBe('Leading and Trailing Spaces');
        });

        it('can create a root category', function () {
            $this->user->givePermissionTo('categories.create');

            $data = [
                'name'      => 'Electronics',
                'is_active' => true,
            ];

            $response = postJson(route('lahatre.catalog.categories.store'), $data);

            $response->assertCreated();
            assertDatabaseHas('catalog_categories', [
                'name'      => 'Electronics',
                'parent_id' => null,
            ]);
        });

        it('can create a child category', function () {
            $this->user->givePermissionTo('categories.create');
            $parent = Category::factory()->create();

            $data = [
                'name'      => 'Smartphones',
                'parent_id' => $parent->id,
                'is_active' => true,
            ];

            $response = postJson(route('lahatre.catalog.categories.store'), $data);

            $response->assertCreated();
            assertDatabaseHas('catalog_categories', [
                'name'      => 'Smartphones',
                'parent_id' => $parent->id,
            ]);
        });
    });

    describe('PATCH /v1/catalog/categories/{category}', function () {
        it('requires permissions to update a category', function () {
            $category = Category::factory()->create();

            patchJson(route('lahatre.catalog.categories.update', $category), ['name' => 'New Name'])
                ->assertForbidden();
        });

        it('can update a category', function () {
            $this->user->givePermissionTo('categories.update');
            $category = Category::factory()->create(['name' => 'Old Name']);

            patchJson(route('lahatre.catalog.categories.update', $category), [
                'name'      => 'New Name',
                'is_active' => true,
            ])->assertOk();

            expect($category->refresh()->name)->toBe('New Name');
        });

        it('fails if the new parent is itself', function () {
            $this->user->givePermissionTo('categories.update');
            $category = Category::factory()->create();

            patchJson(route('lahatre.catalog.categories.update', $category), [
                'name'      => $category->name,
                'is_active' => true,
                'parent_id' => $category->id,
            ])->assertUnprocessable()
              ->assertJsonPath('errors.type', 'CategoryCannotBeDescendantParentException');
        });

        it('fails if the new parent is one of its descendants', function () {
            $this->user->givePermissionTo('categories.update');
            $category = Category::factory()->create();
            $child = Category::factory()->create(['parent_id' => $category->id]);
            $grandchild = Category::factory()->create(['parent_id' => $child->id]);

            patchJson(route('lahatre.catalog.categories.update', $category), [
                'name'      => $category->name,
                'is_active' => true,
                'parent_id' => $grandchild->id,
            ])->assertUnprocessable()
              ->assertJsonPath('errors.type', 'CategoryCannotBeDescendantParentException');
        });
    });

    describe('DELETE /v1/catalog/categories/{category}', function () {
        it('requires permissions to delete a category', function () {
            $category = Category::factory()->create();

            deleteJson(route('lahatre.catalog.categories.destroy', $category))
                ->assertForbidden();
        });

        it('can delete a category without children', function () {
            $this->user->givePermissionTo('categories.delete');
            $category = Category::factory()->create();

            deleteJson(route('lahatre.catalog.categories.destroy', $category))
                ->assertNoContent();

            assertDatabaseMissing('catalog_categories', ['id' => $category->id]);
        });

        it('fails to delete a category with children', function () {
            $this->user->givePermissionTo('categories.delete');
            $category = Category::factory()->create();
            Category::factory()->create(['parent_id' => $category->id]);

            deleteJson(route('lahatre.catalog.categories.destroy', $category))
                ->assertUnprocessable()
                ->assertJsonPath('errors.type', 'CategoryHasChildrenException');

            assertDatabaseHas('catalog_categories', ['id' => $category->id]);
        });
    });

    describe('GET /v1/catalog/categories', function () {
        it('requires permissions to list categories', function () {
            getJson(route('lahatre.catalog.categories.index'))
                ->assertForbidden();
        });

        it('can list categories', function () {
            $this->user->givePermissionTo('categories.list');
            Category::factory()->count(3)->create();

            $response = getJson(route('lahatre.catalog.categories.index'));

            $response->assertOk()
                ->assertJsonCount(3, 'data');
        });
    });

    describe('GET /v1/catalog/categories/{category}', function () {
        it('requires permissions to show a category', function () {
            $category = Category::factory()->create();

            getJson(route('lahatre.catalog.categories.show', $category))
                ->assertForbidden();
        });

        it('can show a category with bloodline', function () {
            $this->user->givePermissionTo('categories.retrieve');
            $category = Category::factory()->create();

            $response = getJson(route('lahatre.catalog.categories.show', $category));

            $response->assertOk()
                ->assertJsonPath('data.id', $category->id)
                ->assertJsonStructure(['data' => ['id', 'name', 'bloodline']]);
        });
    });
});
