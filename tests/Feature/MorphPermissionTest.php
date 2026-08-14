<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Lahatre\Catalog\Models\Product;
use Lahatre\Iam\Models\Permission;
use Lahatre\Shared\Policies\BasePolicy;
use Lahatre\Shared\Registries\MorphMapRegistry;

uses(RefreshDatabase::class);

it('uses morph aliases as model permission namespaces', function (): void {
    $registry = app(MorphMapRegistry::class);
    $policy = new class extends BasePolicy
    {
        public function resolve(string $action, string $model): ?string
        {
            return $this->permissionName($action, $model);
        }
    };

    expect($registry->getAlias(Product::class))->toBe('catalog_product')
        ->and($policy->resolve('retrieve', Product::class))->toBe('catalog_product.retrieve')
        ->and($registry->getModel('catalog_product'))->toBe(Product::class);
});

it('discovers namespaced permissions without basename collisions', function (): void {
    Artisan::call('permissions:discover');

    expect(Permission::query()->where('name', 'catalog_product.retrieve')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'products.retrieve')->exists())->toBeFalse();
});
