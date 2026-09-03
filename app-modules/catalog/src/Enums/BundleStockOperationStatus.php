<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Enums;

enum BundleStockOperationStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
}
