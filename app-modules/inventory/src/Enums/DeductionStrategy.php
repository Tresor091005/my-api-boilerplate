<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Enums;

enum DeductionStrategy: string
{
    case Fifo = 'fifo';
    case Fefo = 'fefo';
    case Manual = 'manual';
}
