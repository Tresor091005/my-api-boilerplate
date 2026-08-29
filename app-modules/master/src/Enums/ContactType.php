<?php

declare(strict_types=1);

namespace Lahatre\Master\Enums;

enum ContactType: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Social = 'social';
    case Website = 'website';
}
