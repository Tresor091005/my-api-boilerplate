<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Catalog\Exceptions\PriceLevelException;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Services\PriceLevelService;
use Lahatre\Customer\Models\Customer;
use Lahatre\Organization\Models\Organization;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember(['customer_customer.create', 'customer_customer.update', 'customer_customer.retrieve']);
    $this->wholesale = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'wholesale']);
});

it('gives a customer a default price level and renders it', function (): void {
    $customerId = $this->postJson('/v1/customer/customers?response=resource&include=price_level', [
        'type'           => 'individual',
        'name'           => 'Revendeur Akpro',
        'is_active'      => true,
        'price_level_id' => $this->wholesale->id,
    ])->assertCreated()
        ->assertJsonPath('data.price_level_id', $this->wholesale->id)
        ->assertJsonPath('data.price_level.name', 'wholesale')
        ->json('data.id');

    $this->patchJson("/v1/customer/customers/{$customerId}?response=resource", ['name' => 'Revendeur Akpro SARL'])
        ->assertOk()
        ->assertJsonPath('data.price_level_id', $this->wholesale->id);

    $this->patchJson("/v1/customer/customers/{$customerId}?response=resource", ['price_level_id' => null])
        ->assertOk()
        ->assertJsonPath('data.price_level_id', null);
});

it('refuses a price level of another organization or a deleted one', function (): void {
    $foreignLevel = PriceLevel::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $deletedLevel = PriceLevel::factory()->create(['organization_id' => $this->organization->id]);
    $deletedLevel->delete();

    foreach ([$foreignLevel, $deletedLevel] as $level) {
        $this->postJson('/v1/customer/customers', [
            'type'           => 'individual',
            'name'           => 'Client',
            'is_active'      => true,
            'is_active'      => true,
            'price_level_id' => $level->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('price_level_id');
    }
});

it('refuses to delete the price level a customer is sold at', function (): void {
    $customer = Customer::factory()->create([
        'organization_id' => $this->organization->id,
        'price_level_id'  => $this->wholesale->id,
    ]);

    expect(fn () => app(PriceLevelService::class)->delete($this->wholesale))
        ->toThrow(PriceLevelException::class);

    $customer->delete();
    app(PriceLevelService::class)->delete($this->wholesale);
    expect(PriceLevel::query()->find($this->wholesale->id))->toBeNull();
});
