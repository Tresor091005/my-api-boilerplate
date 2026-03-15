<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Exceptions;

use Exception;

class TransferBalanceException extends Exception
{
    public function __construct(string $itemId, string $in, string $out, string $baseUnit)
    {
        parent::__construct(
            "Transfer balance mismatch for item {$itemId}. Total IN: {$in} {$baseUnit}, Total OUT: {$out} {$baseUnit}."
        );
    }
}
