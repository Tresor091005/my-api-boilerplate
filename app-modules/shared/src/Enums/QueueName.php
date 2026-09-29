<?php

declare(strict_types=1);

namespace Lahatre\Shared\Enums;

enum QueueName: string
{
    case Default = 'default';
    case Email = 'email';
}
