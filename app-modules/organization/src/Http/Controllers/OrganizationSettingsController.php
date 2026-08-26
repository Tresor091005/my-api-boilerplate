<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Lahatre\Organization\Data\OrganizationSettingsData;
use Lahatre\Organization\Http\Requests\OrganizationSettingsRequest;
use Lahatre\Organization\Http\Resources\OrganizationSettingsResource;
use Lahatre\Organization\Services\OrganizationSettingsService;
use Lahatre\Shared\Http\Responses\ResponseResponder;
use Symfony\Component\HttpFoundation\Response;

final readonly class OrganizationSettingsController
{
    public function __construct(
        private OrganizationSettingsService $settingsService,
        private ResponseResponder $responseResponder,
    ) {}

    public function show(): JsonResponse|Response
    {
        $settings = $this->settingsService->retrieve();
        Gate::authorize('retrieve', $settings);

        return $this->responseResponder->respond(fn (): JsonResource => OrganizationSettingsResource::make($settings));
    }

    public function update(OrganizationSettingsRequest $request): JsonResponse|Response
    {
        $settings = $this->settingsService->retrieve();
        Gate::authorize('update', $settings);
        $settings = $this->settingsService->update(OrganizationSettingsData::fromArray($request->validated()));

        return $this->responseResponder->respond(fn (): JsonResource => OrganizationSettingsResource::make($settings));
    }
}
