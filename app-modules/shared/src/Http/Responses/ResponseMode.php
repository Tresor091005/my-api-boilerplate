<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Responses;

enum ResponseMode: string
{
    case None = 'none';
    case Resource = 'resource';

    public static function defaultForHttpMethod(string $method): self
    {
        return strtoupper($method) === 'GET' ? self::Resource : self::None;
    }
}
