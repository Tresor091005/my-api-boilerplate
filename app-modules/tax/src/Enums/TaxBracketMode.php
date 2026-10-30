<?php

declare(strict_types=1);

namespace Lahatre\Tax\Enums;

enum TaxBracketMode: string
{
    case WholeAmount = 'whole_amount';
    case Marginal = 'marginal';
}
