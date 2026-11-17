<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

enum SaleInvoicing: string
{
    case InvoiceOnConfirmation = 'invoice_on_confirmation';
    case InvoiceOnFulfillment = 'invoice_on_fulfillment';
}
