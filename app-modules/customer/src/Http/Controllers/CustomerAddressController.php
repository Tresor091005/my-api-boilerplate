<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Customer\Http\Requests\AddressCreateRequest;
use Lahatre\Customer\Http\Requests\AddressDeleteRequest;
use Lahatre\Customer\Http\Requests\AddressUpdateRequest;
use Lahatre\Customer\Models\Customer;
use Lahatre\Master\Data\AddressCreateData;
use Lahatre\Master\Data\AddressUpdateData;
use Lahatre\Master\Http\Resources\AddressResource;
use Lahatre\Master\Models\Address;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class CustomerAddressController
{
    public function __construct(private ResponseResponder $responseResponder) {}

    public function store(Customer $customer, AddressCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('update', $customer);
        Gate::authorize('create', Address::class);

        $addresses = array_map(
            AddressCreateData::fromArray(...),
            $request->validated('addresses'),
        );
        $response = $customer->addAddresses($addresses);

        return $this->responseResponder->respond(
            fn (): JsonResource => AddressResource::collection($response),
            status: 201,
        );
    }

    public function update(Customer $customer, Address $address, AddressUpdateRequest $request): JsonResponse|Response
    {
        Gate::authorize('update', $customer);
        Gate::authorize('update', $address);

        $validated = $request->validated();
        $data = AddressUpdateData::fromArray(
            $validated,
            missingFields: ['line', 'city', 'country', 'is_primary'],
        );
        $response = $customer->updateAddress($address, $data);

        return $this->responseResponder->respond(fn (): AddressResource => AddressResource::make($response));
    }

    public function destroy(Customer $customer, AddressDeleteRequest $request): Response
    {
        Gate::authorize('update', $customer);
        Gate::authorize('deleteMany', Address::class);
        $customer->removeAddresses($request->validated('ids'));

        return response()->noContent();
    }
}
