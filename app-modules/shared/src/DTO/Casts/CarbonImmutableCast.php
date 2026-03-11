<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

use Carbon\CarbonImmutable;

class CarbonImmutableCast implements Castable
{
    /**
     * @param  string|null  $format  Optional format for strict parsing (e.g. "d/m/Y")
     */
    public function __construct(protected ?string $format = null) {}

    public function cast(string $key, mixed $value): ?CarbonImmutable
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($this->format) {
            return CarbonImmutable::createFromFormat($this->format, (string) $value);
        }

        return CarbonImmutable::parse((string) $value);
    }
}
