<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Database\Eloquent\Model;

/**
 * Values of computed fields already built in this response, as a builder (FieldComputer,
 * computedBy closure, virtual include loader) sees them. Each builder receives an instance
 * restricted to the keys of its own `fields:`; those fields are built before it runs, and a model
 * for which one of them does not exist is not passed to the builder.
 * A field of `fields:` that is not computed is read on the model: its columns and relations
 * are loaded.
 */
interface BuiltValues
{
    /**
     * Final value of a computed field for a model the builder received. Throws for a key outside
     * the builder's `fields:` or a field that is not computed.
     */
    public function get(Model $model, string $field): mixed;
}
