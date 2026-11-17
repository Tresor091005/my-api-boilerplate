<?php

declare(strict_types=1);

namespace Lahatre\Sale\Enums;

enum SaleApprovalReason: string
{
    /** A required payment share below 100 %. */
    case CreditGranted = 'credit_granted';
}
