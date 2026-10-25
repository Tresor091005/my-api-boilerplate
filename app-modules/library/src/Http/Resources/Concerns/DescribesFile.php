<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources\Concerns;

use Closure;
use Lahatre\Library\Models\File;

/**
 * The file values every library response shows, listed once so they read the same everywhere.
 */
trait DescribesFile
{
    /**
     * Each JSON key with how to read it on a file.
     *
     * @return array<string, Closure(File): mixed>
     */
    protected static function fileValues(): array
    {
        return [
            'name'      => fn (File $file): mixed => $file->name,
            'mime_type' => fn (File $file): mixed => $file->mime_type,
            'kind'      => fn (File $file): string => $file->kind()->value,
            'extension' => fn (File $file): mixed => $file->extension,
            'size'      => fn (File $file): mixed => $file->size,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function describeFile(File $file): array
    {
        return array_map(fn (Closure $value): mixed => $value($file), self::fileValues());
    }
}
