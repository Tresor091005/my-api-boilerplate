<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Lahatre\Billing\Http\Resources\OfferResource;
use Lahatre\Billing\Services\PlanService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class OfferController
{
    public function __construct(private PlanService $planService) {}

    public function index(): JsonResponse|Response
    {
        return ApiResponse::list($this->planService->offers(), OfferResource::class);
    }
}
