<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

/**
 * How a sale runs: when its invoice is born and when its goods leave. The
 * three modes are fixed; a sale never sets its invoicing or delivery itself.
 */
enum SaleMode: string
{
    case Counter = 'counter';
    case OrderInvoicedNow = 'order_invoiced_now';
    case OrderInvoicedOnDelivery = 'order_invoiced_on_delivery';

    public function invoicing(): SaleInvoicing
    {
        return match ($this) {
            self::Counter, self::OrderInvoicedNow => SaleInvoicing::InvoiceOnConfirmation,
            self::OrderInvoicedOnDelivery         => SaleInvoicing::InvoiceOnFulfillment,
        };
    }

    public function delivery(): SaleDelivery
    {
        return match ($this) {
            self::Counter                                         => SaleDelivery::Immediate,
            self::OrderInvoicedNow, self::OrderInvoicedOnDelivery => SaleDelivery::Deferred,
        };
    }
}
