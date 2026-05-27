<?php

declare(strict_types=1);

namespace Lahatre\Pricing\ViewData;

use Illuminate\Support\Collection;
use Lahatre\Pricing\Models\PriceEntry;

class PriceValidationResult
{
    /**
     * @param  Collection<int, PriceEntry>  $applicablePrices
     * @param  Collection<int, PriceEntry>  $matchedPrices
     * @param  array<string, mixed>|null  $bypassAudit
     */
    public function __construct(
        public Collection $applicablePrices,
        public Collection $matchedPrices,
        public bool $isBypassed = false,
        public ?array $bypassAudit = null,
    ) {}

    public function isMatched(): bool
    {
        return $this->matchedPrices->isNotEmpty();
    }
}
