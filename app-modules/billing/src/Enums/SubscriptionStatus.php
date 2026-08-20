<?php

declare(strict_types=1);

namespace Lahatre\Billing\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
}
