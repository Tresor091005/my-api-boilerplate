<?php

declare(strict_types=1);

namespace Lahatre\Shared\Enums;

/**
 * JSON shape of a resource representation, fixed by the ApiResponse factory an action calls:
 * item() → object, list() → list, cursorPage() → {data, meta} cursor page.
 */
enum ResponseShape: string
{
    case Item = 'item';
    case List = 'list';
    case CursorPage = 'cursor_page';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function ofFactory(string $method): ?self
    {
        return match ($method) {
            'item'       => self::Item,
            'list'       => self::List,
            'cursorPage' => self::CursorPage,
            default      => null,
        };
    }

    /**
     * The ApiResponse factory rendering this shape.
     */
    public function factory(): string
    {
        return match ($this) {
            self::Item       => 'item',
            self::List       => 'list',
            self::CursorPage => 'cursorPage',
        };
    }
}
