<?php

declare(strict_types=1);

namespace Lahatre\Master\Tests\Support\Models;

use Lahatre\Master\Contracts\HasTags;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Traits\InteractsWithTags;

class TestTaggableCurrency extends Currency implements HasTags
{
    use InteractsWithTags;

    public function getOrganizationId(): string
    {
        return currentOrganizationId();
    }

    public function getMorphClass(): string
    {
        return new Currency()->getMorphClass();
    }
}
