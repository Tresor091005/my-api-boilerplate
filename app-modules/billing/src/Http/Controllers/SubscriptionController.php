<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Billing\Http\Resources\SubscriptionResource;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Services\SubscriptionService;
use Lahatre\ResponseSelection\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class SubscriptionController
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    public function show(): JsonResponse|Response
    {
        $subscription = $this->subscriptionService->current(currentOrganizationId(), CarbonImmutable::now())
            ?? throw (new ModelNotFoundException)->setModel(Subscription::class);
        Gate::authorize('retrieve', $subscription);

        return ApiResponse::item($subscription, SubscriptionResource::class);
    }
}
