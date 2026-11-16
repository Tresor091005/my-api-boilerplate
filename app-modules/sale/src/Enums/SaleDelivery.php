<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

/**
 * Whether the goods leave at confirmation or in later deliveries.
 */
enum SaleDelivery: string
{
    case Immediate = 'immediate';
    case Deferred = 'deferred';
}
