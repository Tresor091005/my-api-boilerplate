<?php

declare(strict_types=1);

namespace Lahatre\Organization\Services;

use Carbon\CarbonImmutable;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Organization\Enums\ExchangeRateContext;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;

class OrganizationService implements OrganizationInterface
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService,
    ) {}

    public function initializeOrganization(array $data): Organization
    {
        return new Organization;
    }

    public function findOrganizationById(string $organizationId): Organization
    {
        return Organization::query()->findOrFail($organizationId);
    }

    public function quotaBytes(string $organizationId): ?int
    {
        $quota = OrganizationSetting::query()
            ->where('organization_id', $organizationId)
            ->value('storage_quota_bytes');

        return $quota === null ? null : (int) $quota;
    }

    /**
     * @return array{currency_code: string, functional_currency_code: string, amount_in_transaction_currency: string, amount_in_functional_currency: string, exchange_rate: string, exchange_rate_effective_at: CarbonImmutable|null, requested_exchange_context: string, exchange_context: string}
     */
    public function resolveMinorConversion(
        string $amountMinor,
        string $transactionCurrencyCode,
        ExchangeRateContext $context = ExchangeRateContext::Default,
        ?CarbonImmutable $at = null,
    ): array {
        return $this->exchangeRateService->resolveMinorConversion(
            amountMinor: $amountMinor,
            transactionCurrencyCode: $transactionCurrencyCode,
            context: $context,
            at: $at,
        );
    }
}
