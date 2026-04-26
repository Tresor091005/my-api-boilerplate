<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Support\Models;

use Lahatre\Master\Models\Unit;
use Lahatre\Master\Traits\HasTags;

class TestTaggableUnit extends Unit
{
    use HasTags;

    public function getMorphClass(): string
    {
        return (new Unit())->getMorphClass();
    }
}
