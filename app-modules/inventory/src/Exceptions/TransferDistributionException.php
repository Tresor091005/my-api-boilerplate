<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Exception;

class TransferDistributionException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
