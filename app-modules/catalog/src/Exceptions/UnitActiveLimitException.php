<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class UnitActiveLimitException extends AssertionException
{
    public function __construct(int $limit)
    {
        parent::__construct(__('catalog::exceptions.unit_active_limit', ['limit' => $limit]));
    }
}
