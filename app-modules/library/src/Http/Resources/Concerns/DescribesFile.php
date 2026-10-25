<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Resources\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Library\Enums\FileKind;
use Lahatre\Library\Models\File;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * The file values every library response shows, listed once so they read the same everywhere.
 */
trait DescribesFile
{
    /**
     * Each JSON key with the file columns it reads and how to read it.
     *
     * @return array<string, array{columns: list<string>, value: Closure(Model): mixed}>
     */
    protected static function fileFields(): array
    {
        return [
            'name'      => ['columns' => ['name'], 'value' => fn (Model $file): mixed => $file->getAttribute('name')],
            'mime_type' => ['columns' => ['mime_type'], 'value' => fn (Model $file): mixed => $file->getAttribute('mime_type')],
            'kind'      => ['columns' => ['mime_type'], 'value' => fn (Model $file): string => FileKind::fromMimeType((string) $file->getAttribute('mime_type'))->value],
            'extension' => ['columns' => ['extension'], 'value' => fn (Model $file): mixed => $file->getAttribute('extension')],
            'size'      => ['columns' => ['size'], 'value' => fn (Model $file): mixed => $file->getAttribute('size')],
        ];
    }

    /**
     * Each JSON key with how to read it on a file.
     *
     * @return array<string, Closure(Model): mixed>
     */
    protected static function fileValues(): array
    {
        return array_map(fn (array $field): Closure => $field['value'], self::fileFields());
    }

    /**
     * @return array<string, mixed>
     */
    protected static function describeFile(File $file): array
    {
        return array_map(fn (Closure $value): mixed => $value($file), self::fileValues());
    }

    /**
     * Declares one field per file value on a Resource rendering the file itself.
     */
    protected static function defineFileValues(ResourceDefinition $resource): void
    {
        foreach (self::fileFields() as $key => $field) {
            $resource->field($key, columns: $field['columns'], computedBy: $field['value']);
        }
    }
}
