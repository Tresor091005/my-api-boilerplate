<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Customer\Models\Customer;
use Lahatre\Organization\Models\Organization;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->level = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'wholesale']);
});

it('refuses the price levels to a member who can only create customers', function (): void {
    $this->authenticateTaxMember(['customer_customer.create']);

    $this->getJson('/v1/catalog/price-levels')->assertForbidden();
});

it('serves the reference view of the price levels for the purpose of a customer', function (): void {
    $this->authenticateTaxMember(['customer_customer.create']);

    $response = $this->getJson('/v1/catalog/price-levels?for=customer_customer.create')->assertOk();

    $rows = $response->json('data');
    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        expect(array_keys($row))->toEqualCanonicalizing(['id', 'name', 'currency_code']);
    }
});

it('refuses to narrow the view of a purpose', function (string $query, string $key): void {
    $this->authenticateTaxMember(['customer_customer.create']);

    $this->getJson("/v1/catalog/price-levels?for=customer_customer.create&{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors($key);
})->with([
    'fields'  => ['fields=name', 'fields'],
    'include' => ['include=', 'include'],
]);

it('refuses a purpose on a route that has no line for it', function (): void {
    $this->authenticateTaxMember(['customer_customer.create']);

    $this->getJson('/v1/catalog/products?for=customer_customer.create')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('for');
});

it('refuses the purpose to a member who holds only the retrieve permission of customers', function (): void {
    $this->authenticateTaxMember(['customer_customer.retrieve']);

    $this->getJson('/v1/catalog/price-levels?for=customer_customer.create')->assertForbidden();
});

it('refuses the purpose to a member who holds only the normal read permission', function (): void {
    $this->authenticateTaxMember(['catalog_price_level.list']);

    $this->getJson('/v1/catalog/price-levels?for=customer_customer.create')->assertForbidden();
});

it('serves the reference view of one price level of the organization', function (): void {
    $this->authenticateTaxMember(['customer_customer.create']);

    $this->getJson("/v1/catalog/price-levels/{$this->level->id}?for=customer_customer.create")
        ->assertOk()
        ->assertJsonPath('data.id', $this->level->id)
        ->assertJsonPath('data.name', 'wholesale')
        ->assertJsonCount(3, 'data');
});

it('keeps refusing the price level of another organization under a purpose', function (): void {
    $this->authenticateTaxMember(['customer_customer.create', 'catalog_price_level.retrieve']);
    $foreignLevel = PriceLevel::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->getJson("/v1/catalog/price-levels/{$foreignLevel->id}")->assertForbidden();
    $this->getJson("/v1/catalog/price-levels/{$foreignLevel->id}?for=customer_customer.create")->assertForbidden();
});

it('serves the same view for the purpose of an update through the alias', function (): void {
    $this->authenticateTaxMember(['customer_customer.update']);

    $rows = $this->getJson('/v1/catalog/price-levels?for=customer_customer.update')->assertOk()->json('data');
    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        expect(array_keys($row))->toEqualCanonicalizing(['id', 'name', 'currency_code']);
    }
});

it('serves the view, not the full record, to a member who also holds the normal read permission', function (): void {
    $this->authenticateTaxMember(['customer_customer.create', 'catalog_price_level.list']);

    $row = $this->getJson('/v1/catalog/price-levels?for=customer_customer.create')->assertOk()->json('data.0');

    expect(array_keys($row))->toEqualCanonicalizing(['id', 'name', 'currency_code']);
});

it('never opens a write through a purpose', function (): void {
    $this->authenticateTaxMember(['customer_customer.create']);

    $this->postJson('/v1/catalog/price-levels?response=resource&for=customer_customer.create', [
        'name'          => 'retail',
        'currency_code' => 'XOF',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('for');

    expect(PriceLevel::query()->where('organization_id', $this->organization->id)->where('name', 'retail')->exists())->toBeFalse();
});

it('leaves a read without purpose unchanged', function (): void {
    $this->authenticateTaxMember(['catalog_price_level.list']);

    $row = $this->getJson('/v1/catalog/price-levels')->assertOk()->json('data.0');
    expect(array_keys($row))->toContain('created_at', 'updated_at');

    $narrow = $this->getJson('/v1/catalog/price-levels?fields=name')->assertOk()->json('data.0');
    expect(array_keys($narrow))->toEqualCanonicalizing(['id', 'name']);
});

it('creates a customer with the id found through the purpose', function (): void {
    $this->authenticateTaxMember(['customer_customer.create']);

    $id = $this->getJson('/v1/catalog/price-levels?for=customer_customer.create')->assertOk()->json('data.0.id');

    $this->postJson('/v1/customer/customers?response=resource', [
        'type'           => 'individual',
        'name'           => 'Revendeur Akpro',
        'is_active'      => true,
        'price_level_id' => $id,
    ])->assertCreated()
        ->assertJsonPath('data.price_level_id', $id);
});

it('shows the price level of a customer through its reference view', function (): void {
    $this->authenticateTaxMember(['customer_customer.retrieve']);
    $customer = Customer::factory()->create([
        'organization_id' => $this->organization->id,
        'price_level_id'  => $this->level->id,
    ]);

    $child = $this->getJson("/v1/customer/customers/{$customer->id}?include=price_level")
        ->assertOk()
        ->json('data.price_level');

    expect(array_keys($child))->toEqualCanonicalizing(['id', 'name', 'currency_code']);

    $this->getJson("/v1/customer/customers/{$customer->id}?include=price_level(name)")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('include');
});

it('keeps the filters and the paging of the route under a purpose', function (): void {
    $this->authenticateTaxMember(['customer_customer.create']);
    PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'retail']);

    $this->getJson('/v1/catalog/price-levels?for=customer_customer.create&name=whole')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'wholesale');

    $this->getJson('/v1/catalog/price-levels?for=customer_customer.create&per_page=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.per_page', 1);
});
