<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services\Engine;

/**
 * The amount before tax found for a line, and the minor units a contained tax
 * takes so the line amount is found again to the unit.
 */
final readonly class IncludedTaxSolution
{
    /** @param array<string, string> $roundingAdjustments signed minor units, keyed by tax id */
    public function __construct(
        public string $baseAmount,
        public array $roundingAdjustments,
    ) {}
}
