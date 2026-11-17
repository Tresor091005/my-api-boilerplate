<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Enums;

/** A customer hold location keeps, beside one warehouse, the goods already sold and left with us; only the system writes it. */
enum StockLocationKind: string
{
    case Warehouse = 'warehouse';
    case CustomerHold = 'customer_hold';
}
