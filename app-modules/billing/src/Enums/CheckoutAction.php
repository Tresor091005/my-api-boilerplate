<?php

declare(strict_types=1);

namespace Lahatre\Billing\Enums;

enum CheckoutAction: string
{
    case Subscribe = 'subscribe';
    case Renew = 'renew';
    case ChangeNow = 'change_now';
}
