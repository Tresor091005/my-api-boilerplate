<?php

declare(strict_types=1);

namespace Lahatre\Tax\Enums;

enum TaxCalculationType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
