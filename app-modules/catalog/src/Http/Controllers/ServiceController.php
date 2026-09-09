<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Catalog\Data\ServiceData;
use Lahatre\Catalog\Data\ServiceFilterData;
use Lahatre\Catalog\Http\Requests\ServiceCreateRequest;
use Lahatre\Catalog\Http\Requests\ServiceFilterRequest;
use Lahatre\Catalog\Http\Requests\ServiceUpdateRequest;
use Lahatre\Catalog\Http\Resources\ServiceCollection;
use Lahatre\Catalog\Http\Resources\ServiceResource;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Services\ServiceService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class ServiceController
{
    public function __construct(
        private ServiceService $serviceService,
        private ResponseResponder $responseResponder,
    ) {}

    public function index(ServiceFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', Service::class);
        $services = $this->serviceService->paginate(ServiceFilterData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => ServiceCollection::make($services),
        );
    }

    public function store(ServiceCreateRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', Service::class);
        $service = $this->serviceService->create(ServiceData::fromArray($request->validated()));

        return $this->responseResponder->respond(
            fn (): JsonResource => ServiceResource::make($service),
            status: 201,
        );
    }

    public function show(Service $service): JsonResponse|Response
    {
        Gate::authorize('retrieve', $service);
        $service = $this->serviceService->retrieve($service);

        return $this->responseResponder->respond(
            fn (): JsonResource => ServiceResource::make($service),
        );
    }

    public function update(ServiceUpdateRequest $request, Service $service): JsonResponse|Response
    {
        Gate::authorize('update', $service);
        $service = $this->serviceService->update(
            $service,
            ServiceData::fromArray(
                $request->validated(),
                missingFields: ['name', 'sku', 'unit_group_id', 'is_active', 'deliverable_templates'],
            ),
        );

        return $this->responseResponder->respond(
            fn (): JsonResource => ServiceResource::make($service),
        );
    }

    public function destroy(Service $service): Response
    {
        Gate::authorize('delete', $service);
        $this->serviceService->delete($service);

        return response()->noContent();
    }
}
