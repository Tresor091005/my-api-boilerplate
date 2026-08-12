<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Support\Models;

use Lahatre\Master\Models\Currency;
use Lahatre\Master\Traits\HasTags;

class TestTaggableCurrency extends Currency
{
    use HasTags;

    public function getMorphClass(): string
    {
        return new Currency()->getMorphClass();
    }
}
