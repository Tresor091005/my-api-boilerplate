<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lahatre\Catalog\DTO\CategoryDTO;
use Lahatre\Catalog\DTO\CategoryFilterDTO;
use Lahatre\Catalog\Models\Category;
use Lahatre\Catalog\Services\CategoryService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;

uses(RefreshDatabase::class, InteractsWithCatalogTenantContext::class);

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

    expect(collect($payload['data'] ?? [])->pluck('id'))
        ->toContain($category->id)
        ->not->toContain($otherCategory->id);

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
    expect(Category::query()->whereKey($created->id)->exists())->toBeFalse();
});

it('validates category payload via dto', function (): void {
    expect(fn () => new CategoryDTO([]))->toThrow(ValidationException::class);
});
