<?php

declare(strict_types=1);

namespace Lahatre\Tax\Enums;

enum TaxFixedMode: string
{
    case PerLine = 'per_line';
    case PerTaxUnit = 'per_tax_unit';
}
