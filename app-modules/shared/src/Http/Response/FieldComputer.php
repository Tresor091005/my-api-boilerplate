<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

use Illuminate\Database\Eloquent\Collection;

interface FieldComputer
{
    /**
     * Computes $fields for every model at one node in a bounded number of explicit queries
     * (independent of the number of models) and returns their final values by model key, then by
     * field key. $fields holds the keys of this class requested at this node or required by another
     * member's `fields:`; only their dependencies are loaded, so computing another key would read
     * missing columns. The engine keeps the values in ResponseContext, never on the models: a model
     * reached again only computes the fields it lacks, a key missing for a model is omitted (an
     * intended null is returned explicitly), a key outside $fields is dropped. Models refused by a
     * field's `when:` are not passed for that field.
     *
     * @param  array<int, string>  $fields
     * @return array<int|string, array<string, mixed>>
     */
    public function compute(Collection $models, array $fields, BuiltValues $built): array;
}
