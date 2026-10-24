<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Enums;

enum ResponseShape: string
{
    case Item = 'item';
    case List = 'list';
    case CursorPage = 'cursorPage';
}
