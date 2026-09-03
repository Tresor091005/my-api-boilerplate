<?php

declare(strict_types=1);

namespace Lahatre\Customer\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Customer\Data\CustomerData;
use Lahatre\Customer\Enums\CustomerType;
use Lahatre\Customer\Models\Customer;
use Lahatre\Customer\Services\CustomerService;
use Lahatre\Master\Data\AddressCreateData;
use Lahatre\Master\Data\AddressUpdateData;
use Lahatre\Master\Data\ContactCreateData;
use Lahatre\Master\Data\ContactUpdateData;
use Lahatre\Master\Enums\ContactType;

final class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(CustomerService::class);
        $organizationId = currentOrganizationId();

        $customers = [
            [
                'name'                  => 'Acme Industries',
                'type'                  => CustomerType::Company,
                'identification_number' => 'BJ-CIF-0001',
                'is_active'             => true,
                'address'               => ['line' => '12 Avenue de la Marina', 'city' => 'Cotonou', 'country' => 'BJ'],
                'contact'               => ['type' => ContactType::Email, 'value' => 'procurement@acme.example'],
            ],
            [
                'name'      => 'Marie Kossi',
                'type'      => CustomerType::Individual,
                'is_active' => true,
                'address'   => ['line' => '8 Rue des Jardins', 'city' => 'Porto-Novo', 'country' => 'BJ'],
                'contact'   => ['type' => ContactType::Phone, 'value' => '+229 97 00 00 01'],
            ],
            [
                'name'      => 'Dormant Customer',
                'type'      => CustomerType::Individual,
                'is_active' => false,
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Customer::query()
                ->where('organization_id', $organizationId)
                ->where('name', $customerData['name'])
                ->first();

            $payload = [
                'type'                  => $customerData['type']->value,
                'name'                  => $customerData['name'],
                'identification_number' => $customerData['identification_number'] ?? null,
                'is_active'             => $customerData['is_active'],
            ];

            if (!$customer instanceof Customer) {
                $customer = $service->create(CustomerData::fromArray($payload));
            } else {
                $customer = $service->update($customer, CustomerData::fromArray($payload));
            }

            if (isset($customerData['address'])) {
                $address = $customer->addresses()->first();
                $addressPayload = [
                    ...$customerData['address'],
                    'is_primary' => true,
                ];

                if ($address === null) {
                    $customer->addAddresses([AddressCreateData::fromArray($addressPayload)]);
                } else {
                    $customer->updateAddress($address, AddressUpdateData::fromArray($addressPayload));
                }
            }

            if (isset($customerData['contact'])) {
                $contact = $customer->contacts()->first();
                $contactPayload = [
                    'type'       => $customerData['contact']['type']->value,
                    'value'      => $customerData['contact']['value'],
                    'is_primary' => true,
                ];

                if ($contact === null) {
                    $customer->addContacts([ContactCreateData::fromArray($contactPayload)]);
                } else {
                    $customer->updateContact($contact, ContactUpdateData::fromArray($contactPayload));
                }
            }
        }
    }
}
