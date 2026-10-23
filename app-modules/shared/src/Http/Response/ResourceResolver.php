<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

use Illuminate\Database\Eloquent\Model;

interface ResourceResolver
{
    /**
     * The ApiResource class that renders this child model (polymorphic includes), or null when the
     * model has no API representation: nothing is loaded under it, a single include renders `null`
     * and a list include leaves it out. A non-null class must be listed by resources().
     *
     * @return class-string<ApiResource>|null
     */
    public function resourceFor(Model $model): ?string;

    /**
     * Every ApiResource class this resolver may return (definition coverage and API docs).
     *
     * @return array<int, class-string<ApiResource>>
     */
    public function resources(): array;
}
