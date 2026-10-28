<?php

declare(strict_types=1);

namespace Lahatre\Billing\Enums;

enum PlanStatus: string
{
    case OnSale = 'on_sale';
    case Retired = 'retired';
    case Unused = 'unused';

    public static function of(bool $isOnSale, bool $hasBeenSubscribed): self
    {
        return match (true) {
            $isOnSale          => self::OnSale,
            $hasBeenSubscribed => self::Retired,
            default            => self::Unused,
        };
    }
}
