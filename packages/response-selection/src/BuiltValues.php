<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Database\Eloquent\Model;

/**
 * Gives a computer or loader the values of the computed fields it lists in `fields:`.
 * A plain (not computed) field is read on the model itself.
 */
interface BuiltValues
{
    /**
     * The value of a computed field for one of the models received.
     */
    public function get(Model $model, string $field): mixed;
}
