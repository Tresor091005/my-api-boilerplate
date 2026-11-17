<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

/** What a set of sources is for, which decides what they must cover and where the stock leaves from. */
enum FulfillmentPurpose: string
{
    /** A counter sale delivers everything at confirmation, from the warehouses. */
    case CounterConfirmation = 'counter_confirmation';
    /** A bill-and-hold sale sets everything aside at confirmation, in the hold locations of the warehouses. */
    case HoldConfirmation = 'hold_confirmation';
    /** A later fulfillment of a progress-billing sale, from the warehouses. */
    case FromWarehouse = 'from_warehouse';
    /** A later fulfillment of a bill-and-hold sale, from what was set aside. */
    case FromHold = 'from_hold';

    public function coversWholeLines(): bool
    {
        return $this === self::CounterConfirmation || $this === self::HoldConfirmation;
    }

    public function takesFromHold(): bool
    {
        return $this === self::FromHold;
    }
}
