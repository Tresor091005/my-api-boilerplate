<?php

declare(strict_types=1);

namespace Lahatre\Library\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-require-extends Model
 */
interface HasFileSlots
{
    /** @return array<string, array{max_files: int, mime_types: list<string>}> */
    public function fileSlots(): array;
}
