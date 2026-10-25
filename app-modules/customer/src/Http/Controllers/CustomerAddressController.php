<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Customer\Http\Requests\AddressCreateRequest;
use Lahatre\Customer\Http\Requests\AddressDeleteRequest;
use Lahatre\Customer\Http\Requests\AddressUpdateRequest;
use Lahatre\Customer\Models\Customer;
use Lahatre\Master\Data\AddressCreateData;
use Lahatre\Master\Data\AddressUpdateData;
use Lahatre\Master\Http\Resources\AddressResource;
use Lahatre\Master\Models\Address;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class CustomerAddressController
{
    public function store(Customer $customer, AddressCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('update', $customer);

        $addresses = array_map(
            AddressCreateData::fromArray(...),
            $request->validated('addresses'),
        );
        $response = $customer->addAddresses($addresses);

        return ApiResponse::list($response, AddressResource::class, 201);
    }

    public function update(Customer $customer, Address $address, AddressUpdateRequest $request): JsonResponse|Response
    {
        Gate::authorize('update', $customer);

        $validated = $request->validated();
        $data = AddressUpdateData::fromArray(
            $validated,
            missingFields: ['line', 'city', 'country', 'is_primary'],
        );
        $response = $customer->updateAddress($address, $data);

        return ApiResponse::item($response, AddressResource::class);
    }

    public function destroy(Customer $customer, AddressDeleteRequest $request): Response
    {
        Gate::authorize('update', $customer);
        $customer->removeAddresses($request->validated('ids'));

        return ApiResponse::noContent();
    }
}
