<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Concerns;

use Lahatre\Shared\DTO\Casts\ArrayCast;
use Lahatre\Shared\DTO\Casts\BooleanCast;
use Lahatre\Shared\DTO\Casts\CarbonImmutableCast;
use Lahatre\Shared\DTO\Casts\Castable;
use Lahatre\Shared\DTO\Casts\CollectionCast;
use Lahatre\Shared\DTO\Casts\DTOCast;
use Lahatre\Shared\DTO\Casts\EnumCast;
use Lahatre\Shared\DTO\Casts\FloatCast;
use Lahatre\Shared\DTO\Casts\IntegerCast;
use Lahatre\Shared\DTO\Casts\ObjectCast;
use Lahatre\Shared\DTO\Casts\StringCast;

trait HasCasting
{
    /**
     * Hydrate the DTO properties with validated and casted data.
     */
    protected function hydrate(array $data): void
    {
        $casts = $this->casts();
        $this->validatedData = [];

        foreach ($data as $key => $value) {
            $cast = $casts[$key] ?? null;
            $castedValue = $this->castValue($key, $value, $cast);

            if (property_exists($this, $key)) {
                $this->{$key} = $castedValue;
            }

            $this->validatedData[$key] = $castedValue;
        }
    }

    /**
     * Cast a value based on the defined rules.
     */
    protected function castValue(string $key, mixed $value, mixed $cast): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_null($cast)) {
            return $value;
        }

        // Handle Castable instance
        if ($cast instanceof Castable) {
            return $cast->cast($key, $value);
        }

        // Handle Closure cast
        if (is_callable($cast)) {
            return $cast($value);
        }

        // Handle array of something (recursive list)
        if (is_array($cast)) {
            $target = $cast[0] ?? null;
            if ($target) {
                return (new ArrayCast($this->resolveCastable($target)))->cast($key, $value);
            }

            return (array) $value;
        }

        // Handle string types (bool, int, etc) or class names
        if (is_string($cast)) {
            return $this->resolveCastable($cast)->cast($key, $value);
        }

        return $value;
    }

    /**
     * Resolve a string or class name into a Castable instance.
     */
    protected function resolveCastable(string $cast): Castable
    {
        [$type, $param] = str_contains($cast, ':') ? explode(':', $cast, 2) : [$cast, null];

        return match ($type) {
            'bool', 'boolean' => new BooleanCast(),
            'int', 'integer' => new IntegerCast(),
            'float', 'double' => new FloatCast(),
            'string' => new StringCast(),
            'object' => new ObjectCast(),
            'carbonImmutable', 'immutable_datetime' => new CarbonImmutableCast($param),
            'array'      => new ArrayCast($param ? $this->resolveCastable($param) : null),
            'collection' => new CollectionCast($param ? $this->resolveCastable($param) : null),
            default      => $this->resolveComplexCast($cast),
        };
    }

    /**
     * Resolve complex types like DTOs or Enums.
     */
    protected function resolveComplexCast(string $cast): Castable
    {
        if (is_subclass_of($cast, \Lahatre\Shared\DTO\LahatreDTO::class)) {
            return new DTOCast($cast);
        }

        if (enum_exists($cast)) {
            return new EnumCast($cast);
        }

        // Fallback to a custom Castable class if it exists
        if (class_exists($cast) && is_subclass_of($cast, Castable::class)) {
            return new $cast();
        }

        return new StringCast();
    }
}
