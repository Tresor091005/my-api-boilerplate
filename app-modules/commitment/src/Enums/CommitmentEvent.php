<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Enums;

enum CommitmentEvent: string
{
    case Submit = 'submit';
    case Accept = 'accept';
    case Reject = 'reject';
    case RequestClosure = 'request_closure';
    case ConfirmClosure = 'confirm_closure';
    case RejectClosure = 'reject_closure';
    case RequestReopening = 'request_reopening';
    case ConfirmReopening = 'confirm_reopening';
    case RejectReopening = 'reject_reopening';
}
