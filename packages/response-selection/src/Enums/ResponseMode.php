<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Enums;

enum ResponseMode: string
{
    case None = 'none';
    case Resource = 'resource';

    /**
     * Modes an action accepts for this HTTP method, the default first.
     *
     * @return non-empty-list<self>
     */
    public static function allowedFor(string $method, bool $noContentOnly = false): array
    {
        return match (true) {
            $noContentOnly, $method === 'DELETE'     => [self::None],
            in_array($method, ['GET', 'HEAD'], true) => [self::Resource],
            default                                  => [self::None, self::Resource],
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
