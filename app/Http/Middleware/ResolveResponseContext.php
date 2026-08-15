<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lahatre\Shared\Http\Responses\ResponseContext;
use Lahatre\Shared\Registries\ResponseContractRegistry;
use Symfony\Component\HttpFoundation\Response;

final class ResolveResponseContext
{
    public function __construct(
        private ResponseContractRegistry $contracts,
        private ResponseContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $contract = $this->contracts->forRoute($request->route()->getName());

        if ($contract === null) {
            return $next($request);
        }

        $parameters = config('api-responses.parameters');
        $requestedIncludes = $this->parseIncludes($request->query($parameters['include']));
        $shape = $contract->resolveShape($request->query($parameters['shape']));

        $this->context->configure(
            mode: $contract->resolveMode($request->query($parameters['mode']), $request->method()),
            shape: $shape,
            requestedIncludes: $shape?->validateIncludes($requestedIncludes) ?? [],
        );

        return $next($request);
    }

    /** @return list<string> */
    private function parseIncludes(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (!is_string($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }
}
