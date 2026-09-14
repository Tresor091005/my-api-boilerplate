<?php

declare(strict_types=1);

namespace Lahatre\Library\Enums;

enum FileStorageStatus: string
{
    case Available = 'available';
    case Missing = 'missing';
    case Corrupted = 'corrupted';
}
