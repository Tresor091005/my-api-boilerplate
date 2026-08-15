<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Resources\Concerns;

use Closure;
use Illuminate\Http\Resources\MissingValue;
use Lahatre\Shared\Http\Responses\ResponseContext;

trait RendersResponseIncludes
{
    /**
     * @param  string|list<string>  $include
     * @param  Closure(mixed): mixed  $resolver
     */
    protected function includeWhenRequestedAndLoaded(
        string|array $include,
        string $relation,
        Closure $resolver,
    ): mixed {
        $includeNames = is_array($include) ? $include : [$include];
        $context = app(ResponseContext::class);

        if (array_intersect($includeNames, $context->requestedIncludes()) === []) {
            return new MissingValue;
        }

        return $this->whenLoaded($relation, $resolver);
    }
}
