<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Customer\Http\Requests\ContactCreateRequest;
use Lahatre\Customer\Http\Requests\ContactDeleteRequest;
use Lahatre\Customer\Http\Requests\ContactUpdateRequest;
use Lahatre\Customer\Models\Customer;
use Lahatre\Master\Data\ContactCreateData;
use Lahatre\Master\Data\ContactUpdateData;
use Lahatre\Master\Http\Resources\ContactResource;
use Lahatre\Master\Models\Contact;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class CustomerContactController
{
    public function __construct(private ResponseResponder $responseResponder) {}

    public function store(Customer $customer, ContactCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('update', $customer);
        Gate::authorize('create', Contact::class);

        $contacts = array_map(
            ContactCreateData::fromArray(...),
            $request->validated('contacts'),
        );
        $response = $customer->addContacts($contacts);

        return $this->responseResponder->respond(
            fn (): JsonResource => ContactResource::collection($response),
            status: 201,
        );
    }

    public function update(Customer $customer, Contact $contact, ContactUpdateRequest $request): JsonResponse|Response
    {
        Gate::authorize('update', $customer);
        Gate::authorize('update', $contact);

        $validated = $request->validated();
        $data = ContactUpdateData::fromArray(
            $validated,
            missingFields: ['type', 'value', 'is_primary'],
        );
        $response = $customer->updateContact($contact, $data);

        return $this->responseResponder->respond(fn (): ContactResource => ContactResource::make($response));
    }

    public function destroy(Customer $customer, ContactDeleteRequest $request): Response
    {
        Gate::authorize('update', $customer);
        Gate::authorize('deleteMany', Contact::class);
        $customer->removeContacts($request->validated('ids'));

        return response()->noContent();
    }
}
