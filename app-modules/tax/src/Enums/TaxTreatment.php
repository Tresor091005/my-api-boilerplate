<?php

declare(strict_types=1);

namespace Lahatre\Tax\Enums;

enum TaxTreatment: string
{
    case Standard = 'standard';
    case Custom = 'custom';
    case Zero = 'zero';
    case Exempt = 'exempt';
}
