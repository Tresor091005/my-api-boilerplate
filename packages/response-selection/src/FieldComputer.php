<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface FieldComputer
{
    /**
     * Computes fields for all the models at once, in a few queries rather than one per model.
     *
     * Return the final values by model key, then by field key. A field left out is not rendered for
     * that model; to render null, return null.
     *
     *     [
     *         7 => ['line_count' => 2, 'total_quantity' => 15],
     *         9 => ['line_count' => 0, 'total_quantity' => 0],
     *     ]
     *
     * @param  Collection<int, Model>  $models  the models of this level of the response
     * @param  array<int, string>  $fields  which of this class's fields to compute this time, e.g. ['line_count'];
     *                                      compute only these: the columns of the others were not loaded
     * @param  BuiltValues  $built  values of the computed fields listed in their `fields:`
     * @return array<int|string, array<string, mixed>>
     */
    public function compute(Collection $models, array $fields, BuiltValues $built): array;
}
