<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class EmailLoginException extends AssertionException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function invalidCode(): self
    {
        return new self(__('iam::exceptions.auth.invalid_code'));
    }
}
