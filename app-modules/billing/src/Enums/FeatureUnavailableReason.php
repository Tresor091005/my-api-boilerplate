<?php

declare(strict_types=1);

namespace Lahatre\Billing\Enums;

enum FeatureUnavailableReason: string
{
    case Disabled = 'disabled';
    case NoSubscription = 'no_subscription';
    case Suspended = 'suspended';
    case NotInPlan = 'not_in_plan';
    case LimitReached = 'limit_reached';
    case ValueNotInPlan = 'value_not_in_plan';
}
