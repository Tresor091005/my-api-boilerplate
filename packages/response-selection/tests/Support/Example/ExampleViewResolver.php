<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Http\Request;
use Lahatre\ResponseSelection\ViewResolver;

/**
 * Accepts a few purposes of the example routes, each with the view that answers it.
 */
final class ExampleViewResolver implements ViewResolver
{
    private const array VIEWS = [
        'transfer.pick'    => 'compact',
        'depot.pick'       => 'reference',
        'depot.overview'   => 'overview',
        'depot.undeclared' => 'missing_view',
    ];

    public function viewFor(string $purpose, Request $request): ?string
    {
        return self::VIEWS[$purpose] ?? null;
    }
}
