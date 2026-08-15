<?php

declare(strict_types=1);

namespace Lahatre\Shared\Data;

/**
 * Remove only missing-value markers while preserving explicit values.
 *
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
 * Resolve a required value or throw when the value is missing.
 *
 * @template TValue
 *
 * @param  TValue|MissingValue  $value
 * @return TValue
 */
function required(mixed $value): mixed
{
    return MissingValue::required($value);
}
