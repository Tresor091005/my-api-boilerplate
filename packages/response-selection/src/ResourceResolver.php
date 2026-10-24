<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Database\Eloquent\Model;

interface ResourceResolver
{
    /**
     * The Resource that renders this model, or null to render it as null (or leave it out of a list).
     * The class must be one of resources().
     *
     * @return class-string<ApiResource>|null
     */
    public function resourceFor(Model $model): ?string;

    /**
     * Every Resource this resolver may return.
     *
     * @return array<int, class-string<ApiResource>>
     */
    public function resources(): array;
}
