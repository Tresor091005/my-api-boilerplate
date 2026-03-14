<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions\Unit;

use Lahatre\Shared\Exceptions\AssertionException;

class UnitActiveLimitException extends AssertionException
{
    public function __construct(int $limit)
    {
        parent::__construct(__('master::exceptions.unit_active_limit', ['limit' => $limit]));
    }
}
