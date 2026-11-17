<?php

declare(strict_types=1);

namespace Lahatre\Sale\Services;

use Illuminate\Database\Eloquent\Builder;
use Lahatre\Customer\Contracts\CustomerUsageInterface;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Models\Sale;

final class SaleCustomerUsage implements CustomerUsageInterface
{
    private const array UNDER_WAY = [
        SaleStatus::WaitingApproval,
        SaleStatus::Approved,
        SaleStatus::Active,
        SaleStatus::Cancelling,
    ];

    public function isInUse(string $organizationId, string $customerId): bool
    {
        return $this->underWay($organizationId, $customerId)->exists();
    }

    public function followsDueDate(string $organizationId, string $customerId): bool
    {
        return $this->underWay($organizationId, $customerId)
            ->where('required_payment_percent', '<', Sale::FULL_PAYMENT_PERCENT)
            ->whereNull('payment_due_date')
            ->exists();
    }

    /** @return Builder<Sale> */
    private function underWay(string $organizationId, string $customerId): Builder
    {
        return Sale::query()
            ->where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->whereIn('status', array_map(fn (SaleStatus $status): string => $status->value, self::UNDER_WAY));
    }
}
