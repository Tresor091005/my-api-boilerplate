<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

use Carbon\CarbonImmutable;

class CarbonImmutableCast implements Castable
{
    public function __construct(
        private ?string $timezone = null,
        private ?string $format = null
    ) {}

    public function cast(string $key, mixed $value): ?CarbonImmutable
    {
        if (is_null($value)) return null;

        return is_null($this->format)
            ? CarbonImmutable::parse($value, $this->timezone)
            : CarbonImmutable::createFromFormat($this->format, $value, $this->timezone);
    }
}
