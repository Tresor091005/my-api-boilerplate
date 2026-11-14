<?php

declare(strict_types=1);

namespace Lahatre\Tax\Enums;

use RoundingMode;

enum TaxRoundingMode: string
{
    case HalfUp = 'half_up';
    case Up = 'up';
    case Down = 'down';

    public function nativeMode(): RoundingMode
    {
        return match ($this) {
            self::HalfUp => RoundingMode::HalfAwayFromZero,
            self::Up     => RoundingMode::PositiveInfinity,
            self::Down   => RoundingMode::NegativeInfinity,
        };
    }
}
