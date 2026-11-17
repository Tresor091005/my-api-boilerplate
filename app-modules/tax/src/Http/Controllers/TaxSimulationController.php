<?php

declare(strict_types=1);

namespace Lahatre\Tax\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Tax\Data\TaxSimulationData;
use Lahatre\Tax\Http\Requests\TaxSimulationRequest;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Services\TaxResolutionService;
use Lahatre\Tax\Services\TaxResultPresenter;

final readonly class TaxSimulationController
{
    public function __construct(
        private TaxResolutionService $resolutionService,
        private TaxResultPresenter $resultPresenter,
        private OrganizationInterface $organizationInterface,
    ) {}

    public function store(TaxSimulationRequest $request): JsonResponse
    {
        $data = TaxSimulationData::fromArray($request->validated());
        Gate::authorize($data->includeInactive ? 'simulateInactive' : 'simulate', TaxProfile::class);

        $simulation = $this->resolutionService->simulate($data);
        $currencyCode = $this->organizationInterface->functionalCurrency(currentOrganizationId())->code;

        return response()->json(['data' => $this->resultPresenter->presentSimulation($simulation, $currencyCode)]);
    }
}
