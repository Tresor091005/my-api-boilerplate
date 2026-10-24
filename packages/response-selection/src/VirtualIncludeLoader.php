<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface VirtualIncludeLoader
{
    /**
     * Fetches the children of all the parents at once, in a few queries rather than one per parent.
     *
     * Return the children by parent key: one model (or null), or a list of models for an `asList`
     * include. A parent left out has no children.
     *
     *     [3 => $depot, 5 => null]                    // one child per parent
     *     [3 => [$transfer1, $transfer2], 5 => []]    // asList: true
     *
     * @param  Collection<int, Model>  $parents  the models of this level of the response
     * @param  BuiltValues  $built  values of the computed fields listed in the include's `fields:`
     * @return array<int|string, mixed>
     */
    public function load(Collection $parents, BuiltValues $built): array;
}
