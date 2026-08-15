<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Responses;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResponseResponder
{
    public function __construct(private ResponseContext $context) {}

    /** @param Closure(): JsonResource $factory */
    public function respond(Closure $factory, int $status = 200): JsonResponse|Response
    {
        if ($this->context->mode() === ResponseMode::None) {
            return response()->noContent();
        }

        $resource = $factory();
        $response = $resource->response();
        $response->setStatusCode($status);

        return $response;
    }
}
