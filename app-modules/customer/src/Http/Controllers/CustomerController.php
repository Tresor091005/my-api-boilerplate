<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Customer\Data\CustomerData;
use Lahatre\Customer\Data\CustomerFilterData;
use Lahatre\Customer\Http\Requests\CustomerFilterRequest;
use Lahatre\Customer\Http\Requests\CustomerRequest;
use Lahatre\Customer\Http\Resources\CustomerCollection;
use Lahatre\Customer\Http\Resources\CustomerResource;
use Lahatre\Customer\Models\Customer;
use Lahatre\Customer\Services\CustomerService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class CustomerController
{
    public function __construct(
        private CustomerService $customerService,
        private ResponseResponder $responseResponder,
    ) {}

    public function index(CustomerFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Customer::class);
        $filters = CustomerFilterData::fromArray($request->validated());
        $response = $this->customerService->paginate($filters);

        return $this->responseResponder->respond(fn (): JsonResource => CustomerCollection::make($response));
    }

    public function store(CustomerRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Customer::class);
        $data = CustomerData::fromArray($request->validated());
        $response = $this->customerService->create($data);

        return $this->responseResponder->respond(
            fn (): JsonResource => CustomerResource::make($response),
            status: 201,
        );
    }

    public function show(Customer $customer): JsonResponse|Response
    {
        Gate::authorize('retrieve', $customer);
        $response = $this->customerService->retrieve($customer);

        return $this->responseResponder->respond(fn (): JsonResource => CustomerResource::make($response));
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse|Response
    {
        Gate::authorize('update', $customer);
        $data = CustomerData::fromArray($request->validated(), [
            'type', 'name', 'identification_number', 'is_active',
        ]);
        $response = $this->customerService->update($customer, $data);

        return $this->responseResponder->respond(fn (): JsonResource => CustomerResource::make($response));
    }

    public function destroy(Customer $customer): Response
    {
        Gate::authorize('delete', $customer);
        $this->customerService->delete($customer);

        return response()->noContent();
    }
}
