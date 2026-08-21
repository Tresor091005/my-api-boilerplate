<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Support\Models;

use Lahatre\Master\Models\Unit;
use Lahatre\Master\Traits\InteractsWithLabels;

class TestLabelableUnit extends Unit
{
    use InteractsWithLabels;

    public function getMorphClass(): string
    {
        return new Unit()->getMorphClass();
    }
}
