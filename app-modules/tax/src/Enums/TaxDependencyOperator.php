<?php

declare(strict_types=1);

namespace Lahatre\Tax\Enums;

enum TaxDependencyOperator: string
{
    case Add = 'add';
    case Subtract = 'subtract';
}
