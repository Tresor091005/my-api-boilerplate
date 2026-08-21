<?php

declare(strict_types=1);

namespace Lahatre\Master\Enums;

enum NoteKind: string
{
    case Info = 'info';
    case Reminder = 'reminder';
    case Warning = 'warning';
    case Success = 'success';
}
