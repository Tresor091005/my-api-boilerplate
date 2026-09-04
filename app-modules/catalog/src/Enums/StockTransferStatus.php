<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Enums;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
