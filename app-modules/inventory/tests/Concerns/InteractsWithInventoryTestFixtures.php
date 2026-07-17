<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Tests\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lahatre\Inventory\Tests\Fixtures\TestInventoryAltMaterial;
use Lahatre\Inventory\Tests\Fixtures\TestInventoryMaterial;
use Lahatre\Inventory\Tests\Fixtures\TestInventoryWarehouse;

trait InteractsWithInventoryTestFixtures
{
    protected function initializeInventoryTenantContext(): void
    {
        $this->organizationId = Str::uuid7()->toString();
        $this->otherOrganizationId = Str::uuid7()->toString();

        $now = now();
        DB::table('organization_organizations')->insert([
            [
                'id'         => $this->organizationId,
                'name'       => 'Inventory Test Organization',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => $this->otherOrganizationId,
                'name'       => 'Inventory Other Organization',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        setPermissionsTeamId($this->organizationId);
    }

    protected function ensureInventoryTestTables(): void
    {
        Relation::morphMap([
            'test_inventory_material'     => TestInventoryMaterial::class,
            'test_inventory_alt_material' => TestInventoryAltMaterial::class,
            'test_inventory_warehouse'    => TestInventoryWarehouse::class,
        ]);

        if (!Schema::hasTable('test_materials')) {
            Schema::create('test_materials', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('organization_id')->index();
                $table->string('name');
                $table->string('sku')->unique();
                $table->uuid('unit_group_id');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('test_warehouses')) {
            Schema::create('test_warehouses', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('organization_id')->index();
                $table->string('name');
                $table->string('code')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $this->initializeInventoryTenantContext();
    }

    protected function createTestMaterial(array $attributes = []): TestInventoryMaterial
    {
        return TestInventoryMaterial::query()->create(array_merge([
            'organization_id' => $this->organizationId,
            'name'            => fake()->words(2, true),
            'sku'             => fake()->unique()->bothify('SKU-####-????'),
            'unit_group_id'   => (string) $this->group->id,
            'is_active'       => true,
        ], $attributes));
    }

    protected function createTestAltMaterial(array $attributes = []): TestInventoryAltMaterial
    {
        return TestInventoryAltMaterial::query()->create(array_merge([
            'organization_id' => $this->organizationId,
            'name'            => fake()->words(2, true),
            'sku'             => fake()->unique()->bothify('SKU-####-????'),
            'unit_group_id'   => (string) $this->group->id,
            'is_active'       => true,
        ], $attributes));
    }

    protected function createTestWarehouse(array $attributes = []): TestInventoryWarehouse
    {
        return TestInventoryWarehouse::query()->create(array_merge([
            'organization_id' => $this->organizationId,
            'name'            => fake()->company(),
            'code'            => fake()->unique()->bothify('WH-####'),
            'is_active'       => true,
        ], $attributes));
    }
}
