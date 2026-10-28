<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Billing\Http\Resources\FeatureResource;
use Lahatre\Billing\Registries\FeatureRegistry;

final readonly class FeatureController
{
    public function __construct(private FeatureRegistry $features) {}

    public function index(): JsonResponse
    {
        Gate::authorize('operate-platform');

        return FeatureResource::collection($this->features->available())->response();
    }
}
