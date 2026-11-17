<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

enum SaleEvent: string
{
    case Create = 'create';
    case Validate = 'validate';
    case Approve = 'approve';
    case Reject = 'reject';
    case Reopen = 'reopen';
    case Confirm = 'confirm';
    case Abandon = 'abandon';
    case Cancel = 'cancel';
    case Resume = 'resume';
    case CompleteCancellation = 'complete_cancellation';
}
