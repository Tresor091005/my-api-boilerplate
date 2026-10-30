<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Tax\Data\TaxSimulationData;
use Lahatre\Tax\Http\Requests\TaxProfileCalculationRequest;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Services\TaxProfileService;

final readonly class TaxProfileCalculationController
{
    public function __construct(
        private TaxProfileService $profileService,
    ) {}

    public function store(TaxProfileCalculationRequest $request, TaxProfile $profile): JsonResponse
    {
        Gate::authorize('retrieve', $profile);
        $result = $this->profileService->simulate($profile, TaxSimulationData::fromArray($request->validated()));

        return response()->json(['data' => $result]);
    }
}
