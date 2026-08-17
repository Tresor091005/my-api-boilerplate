<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
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
        $routeName = $request->route()->getName();
        $contract = $this->contracts->forRoute($routeName);

        if ($contract === null) {
            throw new InvalidArgumentException(__('shared::exceptions.response_contract_missing', [
                'route' => $routeName ?? $request->path(),
            ]));
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
