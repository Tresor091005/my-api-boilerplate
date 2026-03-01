<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

class UnitBuiltInUpdateException extends AssertionException
{
    public function __construct()
    {
        parent::__construct(__('catalog::exceptions.unit_builtin_update'));
    }
}
