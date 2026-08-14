<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Support\Models;

use Lahatre\Master\Contracts\HasTags;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Traits\InteractsWithTags;

class TestTaggableUnit extends Unit implements HasTags
{
    use InteractsWithTags;

    public function getMorphClass(): string
    {
        return new Unit()->getMorphClass();
    }
}
