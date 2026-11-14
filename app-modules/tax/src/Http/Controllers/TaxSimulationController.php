<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Tax\Data\TaxSimulationData;
use Lahatre\Tax\Http\Requests\TaxSimulationRequest;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Services\TaxResolutionService;

final readonly class TaxSimulationController
{
    public function __construct(
        private TaxResolutionService $resolutionService,
    ) {}

    public function store(TaxSimulationRequest $request): JsonResponse
    {
        $data = TaxSimulationData::fromArray($request->validated());
        Gate::authorize($data->includeInactive ? 'simulateInactive' : 'simulate', TaxProfile::class);

        return response()->json(['data' => $this->resolutionService->simulate($data)]);
    }
}
