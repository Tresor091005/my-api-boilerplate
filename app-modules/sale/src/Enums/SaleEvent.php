<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

/**
 * A gesture that moves a sale, kept in its transitions.
 */
enum SaleEvent: string
{
    case Create = 'create';
    case Validate = 'validate';
    case Approve = 'approve';
    case Reject = 'reject';
    case Withdraw = 'withdraw';
    case Reopen = 'reopen';
    case Confirm = 'confirm';
    case Abandon = 'abandon';
    case Cancel = 'cancel';
}
