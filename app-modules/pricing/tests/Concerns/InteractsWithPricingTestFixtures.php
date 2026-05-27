<?php

declare(strict_types=1);

namespace Lahatre\Pricing\Tests\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lahatre\Pricing\Tests\Fixtures\TestPricingParty;
use Lahatre\Pricing\Tests\Fixtures\TestPricingPriceable;

trait InteractsWithPricingTestFixtures
{
    protected function ensurePricingTestTables(): void
    {
        Relation::morphMap([
            'test_pricing_priceable' => TestPricingPriceable::class,
            'test_pricing_party'     => TestPricingParty::class,
        ]);

        if (!Schema::hasTable('test_pricing_priceables')) {
            Schema::create('test_pricing_priceables', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->string('name');
                $table->string('sku')->unique();
                $table->uuid('unit_group_id');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('test_pricing_parties')) {
            Schema::create('test_pricing_parties', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('organization_id');
                $table->string('name');
                $table->string('code')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    protected function createTestPriceable(array $attributes = []): TestPricingPriceable
    {
        return TestPricingPriceable::query()->create(array_merge([
            'organization_id' => $this->organizationId,
            'name'            => fake()->words(2, true),
            'sku'             => fake()->unique()->bothify('TP-####-????'),
            'unit_group_id'   => (string) $this->unitGroup->id,
            'is_active'       => true,
        ], $attributes));
    }

    protected function createTestParty(array $attributes = []): TestPricingParty
    {
        return TestPricingParty::query()->create(array_merge([
            'organization_id' => $this->organizationId,
            'name'            => fake()->company(),
            'code'            => fake()->unique()->bothify('PTY-####'),
            'is_active'       => true,
        ], $attributes));
    }
}
