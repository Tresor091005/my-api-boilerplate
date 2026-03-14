<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Enums;

enum MovementType: string
{
    case In = 'in';
    case Out = 'out';
}
