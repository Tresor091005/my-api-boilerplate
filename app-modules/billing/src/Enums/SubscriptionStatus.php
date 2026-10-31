<?php

declare(strict_types=1);

namespace Lahatre\Billing\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case InGrace = 'in_grace';
    case Suspended = 'suspended';

    public function grantsAccess(): bool
    {
        return $this !== self::Suspended;
    }
}
