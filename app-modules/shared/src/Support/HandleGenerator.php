<?php

declare(strict_types=1);

namespace Lahatre\Shared\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HandleGenerator
{
    public static function generate(string $name, string $table, int $maxLength = 32): string
    {
        $base = Str::slug(Str::limit($name, $maxLength, ''));

        $handles = DB::table($table)
            ->where('handle', 'LIKE', $base.'%')
            ->pluck('handle');

        if ($handles->isEmpty()) {
            return $base;
        }

        $baseExists = false;
        $maxSuffix = 0;

        foreach ($handles as $handle) {
            if ($handle === $base) {
                $baseExists = true;
                continue;
            }

            if (sscanf($handle, "{$base}-%d", $suffix) === 1 && $suffix > $maxSuffix) {
                $maxSuffix = $suffix;
            }
        }

        if (!$baseExists) {
            return $base;
        }

        $nextSuffix = $maxSuffix + 1;

        return $base.'-'.$nextSuffix;
    }
}
