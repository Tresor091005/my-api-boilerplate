<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\DTO\CategoryDTO;
use Lahatre\Catalog\DTO\CategoryFilterDTO;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Services\CategoryService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

beforeEach(function (): void {
    $this->initializeCatalogTenantContext();
    $this->service = app(CategoryService::class);
});

it('manages categories through service methods and scopes by tenant', function (): void {
    $category = Category::factory()->create([
        'organization_id' => $this->organizationId,
        'name'            => 'Electronics',
    ]);
    $otherCategory = Category::factory()->create([
        'organization_id' => $this->otherOrganizationId,
        'name'            => 'Other Org Category',
    ]);

    $payload = $this->service
        ->list(new CategoryFilterDTO(['per_page' => 50]))
        ->response()
        ->getData(true);

    $categoryIds = collect($payload['data'] ?? [])->pluck('id');

    expect($categoryIds)->toContain($category->id);
    expect($categoryIds->contains($otherCategory->id))->toBeFalse();

    $created = $this->service->create(new CategoryDTO([
        'name'      => 'Smartphones',
        'is_active' => true,
    ]))->resource;

    expect($created->organization_id)->toBe($this->organizationId);

    $updated = $this->service->update($category, new CategoryDTO([
        'name'      => 'Gadgets',
        'is_active' => true,
    ]))->resource;

    expect($updated->name)->toBe('Gadgets');

    $this->service->delete($created);
    expect(Category::query()->whereKey($created->id)->exists())->toBeFalse()
        ->and(Category::withTrashed()->whereKey($created->id)->exists())->toBeTrue()
        ->and(Category::withTrashed()->findOrFail($created->id)->deleted_at)->not->toBeNull();
});

it('rejects soft-deleted category ids in dto relations', function (): void {
    $parent = Category::factory()->create([
        'organization_id' => $this->organizationId,
    ]);
    $deletedCategory = Category::factory()->create([
        'organization_id' => $this->organizationId,
    ]);
    $deletedCategory->delete();

    expect(fn (): CategoryDTO => new CategoryDTO([
        'name'      => 'Child category',
        'parent_id' => $deletedCategory->id,
        'is_active' => true,
    ]))->toThrow(ValidationException::class);

    expect(fn () => $this->service->create(new CategoryDTO([
        'name'      => 'Valid child',
        'parent_id' => $parent->id,
        'is_active' => true,
    ])))->not->toThrow(ValidationException::class);
});

it('validates category payload via dto', function (): void {
    expect(fn (): CategoryDTO => new CategoryDTO([]))->toThrow(ValidationException::class);
});
