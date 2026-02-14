<?php

declare(strict_types=1);

namespace Lahatre\Shared\Exceptions;

use Exception;

abstract class AssertionException extends Exception
{
    /**
     * AssertionException constructor.
     *
     * @param  string  $message  Exception message.
     * @param  array  $context  Additional context for the error.
     */
    public function __construct(string $message = '', protected array $context = [])
    {
        parent::__construct($message);
    }

    public function context(): array
    {
        return $this->context;
    }
}
