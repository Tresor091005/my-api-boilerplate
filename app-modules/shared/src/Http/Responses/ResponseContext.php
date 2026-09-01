<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Responses;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class ResponseContext
{
    private ResponseMode $mode = ResponseMode::Resource;

    private ?ResponseShape $shape = null;

    /** @var list<string> */
    private array $requestedIncludes = [];

    public function configure(
        ResponseMode $mode,
        ?ResponseShape $shape,
        array $requestedIncludes,
    ): void {
        $this->mode = $mode;
        $this->shape = $shape;
        $this->requestedIncludes = $requestedIncludes;
    }

    public function mode(): ResponseMode
    {
        return $this->mode;
    }

    public function shape(): ?ResponseShape
    {
        return $this->shape;
    }

    /** @return list<string> */
    public function requestedIncludes(): array
    {
        return $this->requestedIncludes;
    }

    /** @return list<string> */
    public function relationsToLoad(): array
    {
        if ($this->mode === ResponseMode::None) {
            return [];
        }

        if ($this->shape !== null) {
            return $this->shape->relationsToLoad($this->requestedIncludes);
        }

        return [];
    }

    public function applyToQuery(Builder|QueryBuilder $query): Builder|QueryBuilder
    {
        $relationsToLoad = $this->relationsToLoad();

        if ($query instanceof Builder && $relationsToLoad !== []) {
            $query->with($relationsToLoad);
        }

        return $query;
    }
}
