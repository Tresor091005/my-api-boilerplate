<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Resources\Concerns;

use Closure;
use Illuminate\Http\Resources\MissingValue;
use Lahatre\Shared\Http\Responses\ResponseContext;

trait RendersResponseIncludes
{
    /**
     * @param  Closure(mixed): mixed  $resolver
     */
    protected function includeWhenRequestedAndLoaded(
        string $include,
        string $relation,
        Closure $resolver,
    ): mixed {
        if (!app(ResponseContext::class)->hasRequestedInclude($include)) {
            return new MissingValue;
        }

        return $this->whenLoaded($relation, $resolver);
    }
}
