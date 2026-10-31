<?php

declare(strict_types=1);

namespace Lahatre\Billing\Support;

use Carbon\CarbonImmutable;

final class BillingCalendar
{
    /** Adds $count periods to $start. */
    public function addPeriods(CarbonImmutable $start, int $intervalMonths, int $count): CarbonImmutable
    {
        return $start->addMonthsNoOverflow($intervalMonths * $count);
    }

    /** How many whole periods have passed from $start to $at. */
    public function periodAt(CarbonImmutable $start, int $intervalMonths, CarbonImmutable $at): int
    {
        $period = intdiv((int) $start->diffInMonths($at), $intervalMonths);

        while ($this->addPeriods($start, $intervalMonths, $period + 1) <= $at) {
            $period++;
        }

        while ($period > 0 && $this->addPeriods($start, $intervalMonths, $period) > $at) {
            $period--;
        }

        return $period;
    }
}
