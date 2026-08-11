<?php

declare(strict_types=1);

namespace Lahatre\Shared\Data;

/**
 * @template TValue
 *
 * @param  array<string, TValue|MissingValue>  $values
 * @return array<string, TValue>
 */
function withoutMissing(array $values): array
{
    return MissingValue::withoutMissing($values);
}

/**
 * @template TValue
 *
 * @param  TValue|MissingValue  $value
 * @return TValue
 */
function required(mixed $value): mixed
{
    return MissingValue::required($value);
}
