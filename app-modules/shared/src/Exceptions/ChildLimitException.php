<?php

declare(strict_types=1);

namespace Lahatre\Shared\Exceptions;

use Illuminate\Database\QueryException;

final class ChildLimitException extends AssertionException
{
    public static function reached(string $children, int $max): self
    {
        return new self(__('shared::exceptions.child_limit.reached', ['max' => $max]), [
            'children' => $children,
            'max'      => $max,
        ]);
    }

    /** The error the child limit triggers raise, as `child_limit:<table>.<foreign key>:<max>`; null for any other query error. */
    public static function fromQueryException(QueryException $exception): ?self
    {
        if ($exception->getCode() !== '23514' || preg_match('/child_limit:([a-z0-9_]+\.[a-z0-9_]+):(\d+)/', $exception->getMessage(), $matches) !== 1) {
            return null;
        }

        return self::reached($matches[1], (int) $matches[2]);
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
