<?php

declare(strict_types=1);

namespace Lahatre\Service\Enums;

enum EvidenceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
