<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Casts;

use BackedEnum;

class EnumCast implements Castable
{
    public function __construct(protected string $enumClass) {}

    public function cast(string $key, mixed $value): mixed
    {
        if (is_null($value)) return null;
        if ($value instanceof $this->enumClass) return $value;

        if (is_subclass_of($this->enumClass, BackedEnum::class)) {
            $result = $this->enumClass::tryFrom($value);
            if ($result === null) {
                throw new \ValueError("Invalid value '$value' for enum {$this->enumClass}");
            }
            return $result;
        }

        foreach ($this->enumClass::cases() as $case) {
            if ($case->name === $value) return $case;
        }

        throw new \ValueError("Invalid value '$value' for enum {$this->enumClass}");
    }
}
