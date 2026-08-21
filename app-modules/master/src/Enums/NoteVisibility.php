<?php

declare(strict_types=1);

namespace Lahatre\Master\Enums;

enum NoteVisibility: string
{
    case Organization = 'organization';
    case Mentioned = 'mentioned';
    case Private = 'private';
}
