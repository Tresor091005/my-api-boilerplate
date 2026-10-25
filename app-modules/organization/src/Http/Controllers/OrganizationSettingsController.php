<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Organization\Data\OrganizationSettingsData;
use Lahatre\Organization\Http\Requests\OrganizationSettingsRequest;
use Lahatre\Organization\Http\Resources\OrganizationSettingsResource;
use Lahatre\Organization\Services\OrganizationSettingsService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class OrganizationSettingsController
{
    public function __construct(
        private OrganizationSettingsService $settingsService,
    ) {}

    public function show(): JsonResponse|Response
    {
        $settings = $this->settingsService->retrieve();
        Gate::authorize('retrieve', $settings);

        return ApiResponse::item($settings, OrganizationSettingsResource::class);
    }

    public function update(OrganizationSettingsRequest $request): JsonResponse|Response
    {
        $settings = $this->settingsService->retrieve();
        Gate::authorize('update', $settings);
        $settings = $this->settingsService->update(OrganizationSettingsData::fromArray($request->validated()));

        return ApiResponse::item($settings, OrganizationSettingsResource::class);
    }
}
