<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Enums;

enum BundleStockOperationType: string
{
    case Attach = 'attach';
    case Detach = 'detach';
}
