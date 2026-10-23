<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

use Illuminate\Database\Eloquent\Collection;

interface VirtualIncludeLoader
{
    /**
     * Fetches the children of every parent in a bounded number of explicit queries and returns them
     * by parent key: a child (or null) per parent, or a list of children per parent for an `asList`
     * include. A parent missing from the result has no children (null, or an empty list). The engine
     * keeps the values in ResponseContext, never on the models, renders them and loads the child
     * nodes from them. The parent columns, relations and fields the loader reads are declared on
     * virtualInclude(); parents refused by its `when:` are not passed.
     *
     * @return array<int|string, mixed>
     */
    public function load(Collection $parents, BuiltValues $built): array;
}
