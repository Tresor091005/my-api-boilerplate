<?php

declare(strict_types=1);

namespace Lahatre\Organization\Contracts;

use Carbon\CarbonImmutable;
use Lahatre\Organization\Enums\ExchangeRateContext;
use Lahatre\Organization\Models\Organization;

interface OrganizationInterface
{
    public function initializeOrganization(array $data): Organization;

    public function findOrganizationById(string $organizationId): Organization;

    /**
     * @return array{currency_code: string, functional_currency_code: string, amount_in_transaction_currency: string, amount_in_functional_currency: string, exchange_rate: string, exchange_rate_effective_at: CarbonImmutable|null, requested_exchange_context: string, exchange_context: string}
     */
    public function resolveMinorConversion(
        string $amountMinor,
        string $transactionCurrencyCode,
        ExchangeRateContext $context = ExchangeRateContext::Default,
        ?CarbonImmutable $at = null,
    ): array;
}
