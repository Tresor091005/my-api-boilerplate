<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Customer\Data\CustomerData;
use Lahatre\Customer\Data\CustomerFilterData;
use Lahatre\Customer\Http\Requests\CustomerFilterRequest;
use Lahatre\Customer\Http\Requests\CustomerRequest;
use Lahatre\Customer\Http\Resources\CustomerResource;
use Lahatre\Customer\Models\Customer;
use Lahatre\Customer\Services\CustomerService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class CustomerController
{
    public function __construct(
        private CustomerService $customerService,
    ) {}

    public function index(CustomerFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Customer::class);
        $filters = CustomerFilterData::fromArray($request->validated());
        $response = $this->customerService->paginate($filters);

        return ApiResponse::cursorPage($response, CustomerResource::class);
    }

    public function store(CustomerRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Customer::class);
        $data = CustomerData::fromArray($request->validated());
        $response = $this->customerService->create($data);

        return ApiResponse::item($response, CustomerResource::class, 201);
    }

    public function show(Customer $customer): JsonResponse|Response
    {
        Gate::authorize('retrieve', $customer);
        $response = $this->customerService->retrieve($customer);

        return ApiResponse::item($response, CustomerResource::class);
    }

    public function update(CustomerRequest $request, Customer $customer): JsonResponse|Response
    {
        Gate::authorize('update', $customer);
        $data = CustomerData::fromArray($request->validated(), [
            'type', 'name', 'identification_number', 'is_active',
        ]);
        $response = $this->customerService->update($customer, $data);

        return ApiResponse::item($response, CustomerResource::class);
    }

    public function destroy(Customer $customer): \Illuminate\Http\Response
    {
        Gate::authorize('delete', $customer);
        $this->customerService->delete($customer);

        return ApiResponse::noContent();
    }
}
