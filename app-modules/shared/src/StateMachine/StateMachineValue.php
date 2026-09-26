<?php

declare(strict_types=1);

namespace Lahatre\Shared\StateMachine;

use BackedEnum;
use UnitEnum;

final class StateMachineValue
{
    public static function normalize(UnitEnum $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $value->name;
    }

    public static function enumClass(UnitEnum $value): string
    {
        return $value::class;
    }
}
