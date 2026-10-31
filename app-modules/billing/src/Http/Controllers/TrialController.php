<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Billing\Http\Resources\TrialResource;
use Lahatre\Billing\Models\Trial;
use Lahatre\Billing\Services\TrialService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class TrialController
{
    public function __construct(private TrialService $trialService) {}

    public function show(): JsonResponse|Response
    {
        $trial = $this->trialService->find(currentOrganizationId())
            ?? throw (new ModelNotFoundException)->setModel(Trial::class);
        Gate::authorize('retrieve', $trial);

        return ApiResponse::item($trial, TrialResource::class);
    }
}
