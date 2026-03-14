<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Enums;

enum TransactionType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjustement = 'adjustment';
    case Transfer = 'transfer';
}
