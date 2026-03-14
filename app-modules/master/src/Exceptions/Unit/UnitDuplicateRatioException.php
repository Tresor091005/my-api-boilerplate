<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions\Unit;

use Lahatre\Shared\Exceptions\AssertionException;

class UnitDuplicateRatioException extends AssertionException
{
    public function __construct()
    {
        parent::__construct(__('master::exceptions.unit_duplicate_ratio'));
    }
}
