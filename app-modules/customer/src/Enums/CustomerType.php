<?php

declare(strict_types=1);

namespace Lahatre\Customer\Enums;

enum CustomerType: string
{
    case Individual = 'individual';
    case Company = 'company';
}
