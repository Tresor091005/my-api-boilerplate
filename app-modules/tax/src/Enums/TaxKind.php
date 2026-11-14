<?php

declare(strict_types=1);

namespace Lahatre\Tax\Enums;

enum TaxKind: string
{
    case Vat = 'vat';
    case Other = 'other';
}
