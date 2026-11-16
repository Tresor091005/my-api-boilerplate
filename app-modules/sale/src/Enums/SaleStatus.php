<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

/**
 * Where a sale stands; see the statuses of the sale module documentation.
 */
enum SaleStatus: string
{
    case Draft = 'draft';
    case WaitingApproval = 'waiting_approval';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
    case Abandoned = 'abandoned';
}
