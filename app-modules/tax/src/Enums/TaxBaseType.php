<?php

declare(strict_types=1);

namespace Lahatre\Tax\Enums;

enum TaxBaseType: string
{
    case AmountWithDependencies = 'amount_with_dependencies';
    case DependenciesOnly = 'dependencies_only';
}
