<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions\Unit;

use Lahatre\Shared\Exceptions\AssertionException;

class UnitRatioRequiredException extends AssertionException
{
    public function __construct()
    {
        parent::__construct(__('master::exceptions.unit_ratio_required'));
    }
}
