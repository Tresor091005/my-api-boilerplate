<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Shared\Http\Responses\ResponseContext;
use Lahatre\Shared\Http\Responses\ResponseMode;
use Lahatre\Shared\Http\Responses\ResponseResponder;

it('uses the resource response pipeline for every content status', function (int $status): void {
    $responder = new ResponseResponder(new ResponseContext);

    $response = $responder->respond(
        fn (): JsonResource => JsonResource::make(['name' => 'example'])
            ->additional(['meta' => ['source' => 'test']]),
        $status,
    );

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe($status)
        ->and($response instanceof JsonResponse ? $response->getData(true) : null)->toBe([
            'data' => ['name' => 'example'],
            'meta' => ['source' => 'test'],
        ]);
})->with([200, 201]);

it('returns no content without executing the resource factory', function (): void {
    $context = new ResponseContext;
    $context->configure(ResponseMode::None, null, []);
    $responder = new ResponseResponder($context);
    $factoryCalled = false;

    $response = $responder->respond(function () use (&$factoryCalled): JsonResource {
        $factoryCalled = true;

        return JsonResource::make(['name' => 'example']);
    });

    expect($response->getStatusCode())->toBe(204)
        ->and($factoryCalled)->toBeFalse();
});

it('serializes non-resource payloads through the same response mode', function (): void {
    $responder = new ResponseResponder(new ResponseContext);

    $response = $responder->respond(
        fn (): array => ['detail' => true],
    );

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response instanceof JsonResponse ? $response->getData(true) : null)->toBe(['detail' => true]);
});
