<?php

declare(strict_types=1);

namespace Lahatre\Sale\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Http\Requests\SaleSettingUpdateRequest;
use Lahatre\Sale\Models\SaleSetting;
use Lahatre\Sale\Services\SaleSettingService;

final readonly class SaleSettingController
{
    public function __construct(
        private SaleSettingService $saleSettingService,
    ) {}

    public function show(): JsonResponse
    {
        Gate::authorize('retrieve', SaleSetting::class);

        return response()->json(['data' => $this->saleSettingService->retrieve()]);
    }

    public function update(SaleSettingUpdateRequest $request): JsonResponse
    {
        Gate::authorize('update', SaleSetting::class);
        $settings = $this->saleSettingService->update(SaleMode::from((string) $request->validated('default_mode')));

        return response()->json(['data' => $settings]);
    }
}
