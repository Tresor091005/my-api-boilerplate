<?php

declare(strict_types=1);

namespace Lahatre\Billing\Enums;

enum CheckoutStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
