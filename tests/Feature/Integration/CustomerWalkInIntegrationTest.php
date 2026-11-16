<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Lahatre\Customer\Contracts\CustomerInterface;
use Lahatre\Customer\Exceptions\CustomerException;
use Lahatre\Customer\Models\Customer;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->authenticateTaxMember([
        'customer_customer.create',
        'customer_customer.update',
        'customer_customer.retrieve',
        'customer_customer.delete',
    ]);
});

function walkInCustomer(?string $organizationId = null): Customer
{
    return DB::transaction(fn (): Customer => app(CustomerInterface::class)->walkInCustomer($organizationId ?? currentOrganizationId()));
}

/** @param array<string, mixed> $overrides */
function customerPayload(array $overrides = []): array
{
    return ['type' => 'individual', 'name' => 'Client', 'is_active' => true, ...$overrides];
}

it('validates, stores, empties and renders the payment due date', function (): void {
    $dueDate = now()->addDays(30)->toDateString();
    $customerId = $this->postJson('/v1/customer/customers?response=resource', customerPayload(['payment_due_date' => $dueDate]))
        ->assertCreated()
        ->assertJsonPath('data.payment_due_date', $dueDate)
        ->json('data.id');

    expect(Customer::query()->whereKey($customerId)->value('payment_due_date')->toDateString())->toBe($dueDate);

    $this->patchJson("/v1/customer/customers/{$customerId}?response=resource", ['name' => 'Client 2'])
        ->assertOk()
        ->assertJsonPath('data.payment_due_date', $dueDate);

    $this->patchJson("/v1/customer/customers/{$customerId}?response=resource", ['payment_due_date' => now()->toDateString()])
        ->assertOk()
        ->assertJsonPath('data.payment_due_date', now()->toDateString());

    $this->patchJson("/v1/customer/customers/{$customerId}?response=resource", ['payment_due_date' => null])
        ->assertOk()
        ->assertJsonPath('data.payment_due_date', null);

    $this->postJson('/v1/customer/customers?response=resource', customerPayload())
        ->assertCreated()
        ->assertJsonPath('data.payment_due_date', null);

    $this->getJson("/v1/customer/customers/{$customerId}")
        ->assertOk()
        ->assertJsonPath('data.payment_due_date', null);
});

it('refuses a payment due date in the past or not written Y-m-d', function (mixed $dueDate): void {
    $this->postJson('/v1/customer/customers', customerPayload(['payment_due_date' => $dueDate]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_due_date');

    $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->patchJson("/v1/customer/customers/{$customer->id}", ['payment_due_date' => $dueDate])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_due_date');
})->with([
    'yesterday'       => fn (): string => now()->subDay()->toDateString(),
    'another format'  => fn (): string => now()->addDay()->format('d/m/Y'),
    'a date and time' => fn (): string => now()->addDay()->toIso8601String(),
    'not a date'      => ['soon'],
    'a number'        => [30],
]);

it('reads today in the timezone of the organization for the payment due date', function (): void {
    // 20:00 in UTC is already the 16th at Kiritimati (UTC+14)
    $this->travelTo(CarbonImmutable::parse('2026-11-15 20:00:00', 'UTC'));
    OrganizationSetting::factory()->create(['organization_id' => $this->organization->id, 'timezone' => 'Pacific/Kiritimati']);

    $this->postJson('/v1/customer/customers', customerPayload(['payment_due_date' => '2026-11-15']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_due_date');
    $this->postJson('/v1/customer/customers', customerPayload(['payment_due_date' => '2026-11-16']))->assertNoContent();
});

it('renders is_walk_in and never accepts it from a request', function (): void {
    $customerId = $this->postJson('/v1/customer/customers?response=resource', customerPayload(['is_walk_in' => true]))
        ->assertCreated()
        ->assertJsonPath('data.is_walk_in', false)
        ->json('data.id');

    $this->patchJson("/v1/customer/customers/{$customerId}?response=resource", ['is_walk_in' => true])
        ->assertOk()
        ->assertJsonPath('data.is_walk_in', false);

    $walkIn = walkInCustomer();

    $this->patchJson("/v1/customer/customers/{$walkIn->id}?response=resource", ['is_walk_in' => false])
        ->assertOk()
        ->assertJsonPath('data.is_walk_in', true);

    $this->getJson("/v1/customer/customers/{$walkIn->id}")
        ->assertOk()
        ->assertJsonPath('data.is_walk_in', true)
        ->assertJsonPath('data.name', __('customer::messages.walk_in_customer_name'));
});

it('creates the walk-in customer once and gives the same one afterwards', function (): void {
    $first = walkInCustomer();
    $second = walkInCustomer();

    expect($second->id)->toBe($first->id)
        ->and($first->organization_id)->toBe($this->organization->id)
        ->and($first->is_walk_in)->toBeTrue()
        ->and($first->is_active)->toBeTrue()
        ->and($first->name)->toBe('Walk-in customer')
        ->and(Customer::query()->where('organization_id', $this->organization->id)->where('is_walk_in', true)->count())->toBe(1);
});

it('gives each organization its own walk-in customer', function (): void {
    $mine = walkInCustomer();

    $otherOrganization = Organization::factory()->create();
    $theirs = walkInCustomer($otherOrganization->id);

    expect($theirs->id)->not->toBe($mine->id)
        ->and($theirs->organization_id)->toBe($otherOrganization->id)
        ->and(Customer::query()->where('is_walk_in', true)->whereIn('organization_id', [$this->organization->id, $otherOrganization->id])->count())->toBe(2);
});

it('lets the database refuse a second walk-in customer of an organization', function (): void {
    walkInCustomer();

    expect(fn () => DB::transaction(fn () => Customer::factory()->create([
        'organization_id' => $this->organization->id,
        'is_walk_in'      => true,
    ])))->toThrow(QueryException::class);
});

it('refuses to delete the walk-in customer and says what to do instead', function (): void {
    $walkIn = walkInCustomer();

    $this->deleteJson("/v1/customer/customers/{$walkIn->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', __('customer::exceptions.walk_in_cannot_be_deleted'))
        ->assertJsonPath('errors.type', class_basename(CustomerException::class));

    expect(Customer::query()->find($walkIn->id))->not->toBeNull()
        ->and(__('customer::exceptions.walk_in_cannot_be_deleted'))->toContain('counter sales', 'Rename it or deactivate it');
});

it('lets the walk-in customer be renamed and deactivated', function (): void {
    $walkIn = walkInCustomer();

    $this->patchJson("/v1/customer/customers/{$walkIn->id}?response=resource", ['name' => 'Comptoir', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.name', 'Comptoir')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.is_walk_in', true);

    expect(walkInCustomer()->id)->toBe($walkIn->id);
});

it('still deletes an ordinary customer', function (): void {
    $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

    $this->deleteJson("/v1/customer/customers/{$customer->id}")->assertNoContent();

    expect(Customer::query()->find($customer->id))->toBeNull();
});
