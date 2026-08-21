<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Support\Models;

use Lahatre\Master\Models\Currency;
use Lahatre\Master\Traits\InteractsWithLabels;

class TestLabelableCurrency extends Currency
{
    use InteractsWithLabels;

    public function getMorphClass(): string
    {
        return new Currency()->getMorphClass();
    }
}
