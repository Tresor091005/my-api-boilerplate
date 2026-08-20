<?php

declare(strict_types=1);

namespace Lahatre\Billing\Enums;

enum CollectionMethod: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
}
