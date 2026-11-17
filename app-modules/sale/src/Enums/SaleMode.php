<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

/** A sale never sets its invoicing or delivery itself; its mode decides both. */
enum SaleMode: string
{
    case Counter = 'counter';
    case BillAndHold = 'bill_and_hold';
    case ProgressBilling = 'progress_billing';

    public function invoicing(): SaleInvoicing
    {
        return match ($this) {
            self::Counter, self::BillAndHold => SaleInvoicing::InvoiceOnConfirmation,
            self::ProgressBilling            => SaleInvoicing::InvoiceOnFulfillment,
        };
    }

    public function delivery(): SaleDelivery
    {
        return match ($this) {
            self::Counter                            => SaleDelivery::Immediate,
            self::BillAndHold, self::ProgressBilling => SaleDelivery::Deferred,
        };
    }
}
