<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions\Unit;

use Lahatre\Shared\Exceptions\AssertionException;

class UnitRatioConflictException extends AssertionException
{
    public function __construct(float|int $ratio, string $group)
    {
        parent::__construct(__('catalog::exceptions.unit_ratio_exists_in_group', ['ratio' => $ratio, 'group' => $group]));
    }
}
