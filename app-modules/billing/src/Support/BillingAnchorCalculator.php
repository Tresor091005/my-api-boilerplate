<?php

declare(strict_types=1);

namespace Lahatre\Billing\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class BillingAnchorCalculator
{
    public function periodEnd(CarbonImmutable $periodStart, int $anchorDay, int $months = 1): CarbonImmutable
    {
        if ($anchorDay < 1 || $anchorDay > 31) {
            throw new InvalidArgumentException('Billing anchor day must be between 1 and 31.');
        }

        if ($months < 1) {
            throw new InvalidArgumentException('Billing interval must be at least one month.');
        }

        $targetMonth = $periodStart->startOfMonth()->addMonthsNoOverflow($months);
        $day = min($anchorDay, $targetMonth->daysInMonth);

        return $targetMonth->setDay($day)->setTime(
            $periodStart->hour,
            $periodStart->minute,
            $periodStart->second,
        );
    }
}
