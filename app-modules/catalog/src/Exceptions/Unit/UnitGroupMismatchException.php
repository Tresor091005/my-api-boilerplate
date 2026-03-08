<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions\Unit;

use Lahatre\Shared\Exceptions\AssertionException;

class UnitGroupMismatchException extends AssertionException
{
    public function __construct(string $unitId, string $group)
    {
        parent::__construct(__('catalog::exceptions.unit_group_mismatch', ['unit_id' => $unitId, 'group' => $group]));
    }
}
