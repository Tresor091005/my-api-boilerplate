<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

/** Only a draft is deleted; a completed fulfillment is cancelled, which gives its stock back. */
enum FulfillmentStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
