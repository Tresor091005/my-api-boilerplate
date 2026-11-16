<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Lahatre\ResponseSelection\ApiResponse;

final class ExampleTransferController
{
    /** The purpose the last `depots` call saw while it ran. */
    public static ?string $purposeSeen = null;

    public function depots(): JsonResponse|Response
    {
        self::$purposeSeen = ApiResponse::purpose();

        return ApiResponse::list(ExampleDepot::query()->orderBy('id')->get(), ExampleDepotResource::class);
    }

    public function storeDepot(): JsonResponse|Response
    {
        return ApiResponse::item(ExampleDepot::query()->orderBy('id')->firstOrFail(), ExampleDepotResource::class, 201);
    }

    public function index(): JsonResponse|Response
    {
        return ApiResponse::list(ExampleTransfer::query()->orderBy('id')->get(), ExampleTransferResource::class);
    }

    public function show(int $transfer): JsonResponse|Response
    {
        return ApiResponse::item(ExampleTransfer::query()->findOrFail($transfer), ExampleTransferResource::class);
    }

    public function summaries(): JsonResponse|Response
    {
        return ApiResponse::list(ExampleTransfer::query()->orderBy('id')->get(), ExampleSummaryTransferResource::class);
    }

    public function summary(int $transfer): JsonResponse|Response
    {
        return ApiResponse::item(ExampleTransfer::query()->findOrFail($transfer), ExampleSummaryTransferResource::class);
    }

    public function undeclaredColumn(): JsonResponse|Response
    {
        return ApiResponse::list(ExampleTransfer::query()->orderBy('id')->get(), ExampleUndeclaredColumnTransferResource::class);
    }

    public function queryingClosure(): JsonResponse|Response
    {
        return ApiResponse::list(ExampleTransfer::query()->orderBy('id')->get(), ExampleQueryingClosureTransferResource::class);
    }
}
