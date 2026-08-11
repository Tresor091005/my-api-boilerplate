<?php

declare(strict_types=1);

namespace Lahatre\Shared\Data;

use InvalidArgumentException;
use LogicException;

enum MissingValue
{
    case Instance;

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(
        array $data,
        string $field,
        array $missingFields = [],
        mixed $default = self::Instance,
    ): mixed {
        if (array_key_exists($field, $data)) {
            return $data[$field];
        }

        if (in_array($field, $missingFields, true)) {
            return self::Instance;
        }

        if ($default !== self::Instance) {
            return $default;
        }

        throw new InvalidArgumentException("Missing required data field [{$field}].");
    }

    /**
     * @template TValue
     *
     * @param  array<string, TValue|self>  $values
     * @return array<string, TValue>
     */
    public static function withoutMissing(array $values): array
    {
        return array_filter(
            $values,
            fn (mixed $value): bool => $value !== self::Instance,
        );
    }

    /**
     * @template TValue
     *
     * @param  TValue|self  $value
     * @return TValue
     */
    public static function required(mixed $value): mixed
    {
        if ($value === self::Instance) {
            throw new LogicException('A required data value is missing.');
        }

        return $value;
    }
}
