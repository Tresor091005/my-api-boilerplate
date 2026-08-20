<?php

declare(strict_types=1);

namespace Lahatre\Billing\Enums;

enum FeatureType: string
{
    case Boolean = 'boolean';
    case Capacity = 'capacity';
}
