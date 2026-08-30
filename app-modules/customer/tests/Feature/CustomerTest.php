<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lahatre\Customer\Data\CustomerData;
use Lahatre\Customer\Data\CustomerFilterData;
use Lahatre\Customer\Http\Requests\CustomerRequest;
use Lahatre\Customer\Models\Customer;
use Lahatre\Customer\Services\CustomerService;
use Lahatre\Customer\Tests\Concerns\InteractsWithCustomerTenantContext;
use Lahatre\Master\Data\AddressCreateData;
use Lahatre\Master\Data\AddressUpdateData;
use Lahatre\Master\Data\ContactCreateData;
use Lahatre\Master\Data\ContactUpdateData;
use Lahatre\Master\Models\Address;
use Lahatre\Master\Models\Contact;
use Lahatre\Master\Validation\AddressPayloadRules;

uses(RefreshDatabase::class, InteractsWithCustomerTenantContext::class);

beforeEach(function (): void {
    $this->initializeCustomerTenantContext();
    $this->service = app(CustomerService::class);
});

it('creates, updates, reads, and soft deletes a customer with polymorphic contacts and address', function (): void {
    $customer = $this->service->create(CustomerData::fromArray([
        'type'                  => 'company',
        'name'                  => 'Boutique Soleil',
        'identification_number' => 'IFU-123',
        'is_active'             => true,
    ]));
    $customer->addAddresses([
        AddressCreateData::fromArray([
            'line'       => 'Rue 123',
            'city'       => 'Cotonou',
            'country'    => 'Benin',
            'is_primary' => true,
        ]),
        AddressCreateData::fromArray([
            'line'    => 'Rue 456',
            'city'    => 'Porto-Novo',
            'country' => 'Benin',
        ]),
    ]);
    $customer->addContacts([
        ContactCreateData::fromArray([
            'type'       => 'social',
            'value'      => '+22900000000',
            'is_primary' => true,
        ]),
        ContactCreateData::fromArray([
            'type'  => 'email',
            'value' => 'old@example.test',
        ]),
    ]);

    expect($customer->type->value)->toBe('company')
        ->and($customer->addresses)->toHaveCount(2)
        ->and($customer->contacts)->toHaveCount(2)
        ->and($customer->contacts->first()->type->value)->toBe('social');

    $addressToKeep = $customer->addresses->firstWhere('is_primary', true);
    $addressToRemove = $customer->addresses->firstWhere('is_primary', false);
    $contactToKeep = $customer->contacts->firstWhere('is_primary', true);
    $contactToRemove = $customer->contacts->firstWhere('is_primary', false);

    $updated = $this->service->update($customer, CustomerData::fromArray([
        'name' => 'Boutique Soleil Updated',
    ], missingFields: ['type', 'identification_number', 'is_active']));
    $updated->updateAddress($addressToKeep, AddressUpdateData::fromArray(
        ['line' => 'Rue 789'],
        missingFields: ['city', 'country', 'is_primary'],
    ));
    $updated->updateContact($contactToKeep, ContactUpdateData::fromArray([
        'type'  => 'email',
        'value' => 'hello@example.test',
    ], missingFields: ['is_primary']));
    $updated->removeAddresses([$addressToRemove->id]);
    $updated->removeContacts([$contactToRemove->id]);
    $updated->load(['addresses', 'contacts']);

    expect($updated->name)->toBe('Boutique Soleil Updated')
        ->and($updated->addresses)->toHaveCount(1)
        ->and($updated->addresses->first()->id)->toBe($addressToKeep->id)
        ->and($updated->addresses->first()->line)->toBe('Rue 789')
        ->and($updated->contacts->first()->type->value)->toBe('email');
    expect(Address::withTrashed()->find($addressToRemove->id)->trashed())->toBeTrue()
        ->and(Contact::withTrashed()->find($contactToRemove->id)->trashed())->toBeTrue()
        ->and($updated->addresses->first()->is_primary)->toBeTrue()
        ->and($updated->contacts->first()->is_primary)->toBeTrue();

    $this->service->delete($updated);

    expect(Customer::query()->whereKey($updated->id)->exists())->toBeFalse()
        ->and(Customer::withTrashed()->whereKey($updated->id)->exists())->toBeTrue();
});

it('scopes customers to the current organization', function (): void {
    $customer = Customer::factory()->create(['organization_id' => $this->organizationId]);
    $otherCustomer = Customer::factory()->create(['organization_id' => $this->otherOrganizationId]);

    expect($this->service->paginate(CustomerFilterData::fromArray([]))->getCollection()->pluck('id')->all())->toContain($customer->id)
        ->not->toContain($otherCustomer->id);
});

it('rejects mutations for a customer from another organization', function (): void {
    $otherCustomer = Customer::factory()->create([
        'organization_id' => $this->otherOrganizationId,
    ]);

    expect(fn () => $this->service->update(
        $otherCustomer,
        CustomerData::fromArray(
            ['name' => 'Unauthorized update'],
            missingFields: ['type', 'identification_number', 'is_active'],
        ),
    ))->toThrow(ModelNotFoundException::class);

    expect(fn () => $this->service->delete($otherCustomer))
        ->toThrow(ModelNotFoundException::class);

    expect(Customer::withTrashed()->find($otherCustomer->id)->deleted_at)->toBeNull()
        ->and(Customer::find($otherCustomer->id)->name)->not->toBe('Unauthorized update');
});

it('requires an identification number for company customers', function (): void {
    $request = CustomerRequest::create('/', 'POST', [
        'type' => 'company',
        'name' => 'No Number Company',
    ])->setContainer(app())->setRedirector(app('redirect'));

    expect(fn () => $request->validateResolved())->toThrow(ValidationException::class);
});

it('accepts secondary addresses and contacts when a primary already exists', function (): void {
    $customer = $this->service->create(CustomerData::fromArray([
        'type'      => 'individual',
        'name'      => 'Existing Primary Customer',
        'is_active' => true,
    ]));
    $customer->addAddresses([AddressCreateData::fromArray([
        'line'       => 'Rue 123',
        'city'       => 'Cotonou',
        'country'    => 'Benin',
        'is_primary' => true,
    ])]);
    $customer->addContacts([ContactCreateData::fromArray([
        'type'       => 'email',
        'value'      => 'primary@example.test',
        'is_primary' => true,
    ])]);

    $customer->addAddresses([AddressCreateData::fromArray([
        'line'    => 'Rue 456',
        'city'    => 'Porto-Novo',
        'country' => 'Benin',
    ])]);
    $customer->addContacts([ContactCreateData::fromArray([
        'type'  => 'phone',
        'value' => '+22900000000',
    ])]);
    $updated = $customer->fresh()->load(['addresses', 'contacts']);

    expect($updated->addresses)->toHaveCount(2)
        ->and($updated->addresses->where('is_primary', true))->toHaveCount(1)
        ->and($updated->contacts)->toHaveCount(2)
        ->and($updated->contacts->where('is_primary', true))->toHaveCount(1);
});

it('accepts a free-form country name', function (): void {
    $input = ['addresses' => [[
        'line'       => 'Rue 123',
        'city'       => 'Cotonou',
        'country'    => 'A fictional country',
        'is_primary' => true,
    ]]];
    $validator = Validator::make($input, AddressPayloadRules::rules());

    expect($validator->fails())->toBeFalse();
});
