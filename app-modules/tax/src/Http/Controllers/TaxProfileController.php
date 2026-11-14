<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\Tax\Data\TaxProfileData;
use Lahatre\Tax\Data\TaxProfileFilterData;
use Lahatre\Tax\Http\Requests\TaxProfileFilterRequest;
use Lahatre\Tax\Http\Requests\TaxProfileRequest;
use Lahatre\Tax\Http\Resources\TaxProfileResource;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Services\TaxProfileService;
use Symfony\Component\HttpFoundation\Response;

final readonly class TaxProfileController
{
    public function __construct(
        private TaxProfileService $profileService,
    ) {}

    public function index(TaxProfileFilterRequest $request): JsonResponse|Response
    {
        Gate::authorize('list', TaxProfile::class);
        $profiles = $this->profileService->paginate(TaxProfileFilterData::fromArray($request->validated()));

        return ApiResponse::cursorPage($profiles, TaxProfileResource::class);
    }

    public function store(TaxProfileRequest $request): JsonResponse|Response
    {
        Gate::authorize('create', TaxProfile::class);
        $profile = $this->profileService->create(TaxProfileData::fromArray($request->validated()));

        return ApiResponse::item($profile, TaxProfileResource::class, 201);
    }

    public function show(TaxProfile $profile): JsonResponse|Response
    {
        Gate::authorize('retrieve', $profile);
        $profile = $this->profileService->retrieve($profile);

        return ApiResponse::item($profile, TaxProfileResource::class);
    }

    public function update(TaxProfileRequest $request, TaxProfile $profile): JsonResponse|Response
    {
        Gate::authorize('update', $profile);
        $profile = $this->profileService->update($profile, TaxProfileData::fromArray($request->validated()));

        return ApiResponse::item($profile, TaxProfileResource::class);
    }

    public function destroy(TaxProfile $profile): Response
    {
        Gate::authorize('delete', $profile);
        $this->profileService->delete($profile);

        return ApiResponse::noContent();
    }
}
