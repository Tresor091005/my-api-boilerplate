<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Resources;

use Lahatre\Shared\Http\Resources\BaseCollection;

class NoteCollection extends BaseCollection
{
    public $collects = NoteResource::class;
}
