<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

enum SaleDelivery: string
{
    case Immediate = 'immediate';
    case Deferred = 'deferred';
}
