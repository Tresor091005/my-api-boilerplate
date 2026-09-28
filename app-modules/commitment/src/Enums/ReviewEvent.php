<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Enums;

enum ReviewEvent: string
{
    case Submit = 'submit';
    case Accept = 'accept';
    case RequestChanges = 'request_changes';
    case Reject = 'reject';
    case Revoke = 'revoke';
}
