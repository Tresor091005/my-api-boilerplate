<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Lahatre\ResponseSelection\ApiResponse;

final class ExampleTransferController
{
    public function index(): JsonResponse|Response
    {
        return ApiResponse::list(ExampleTransfer::query()->orderBy('id')->get(), ExampleTransferResource::class);
    }

    public function show(int $transfer): JsonResponse|Response
    {
        return ApiResponse::item(ExampleTransfer::query()->findOrFail($transfer), ExampleTransferResource::class);
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
