<?php

declare(strict_types=1);

namespace Lahatre\Tax\Services;

use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Currency;
use Lahatre\Organization\Contracts\OrganizationInterface;

/**
 * Taxes are denominated in the organization functional currency, which never
 * changes, so the lookup is memoized for the request.
 */
final class FunctionalCurrencyResolver
{
    /** @var array<string, Currency> */
    private array $currencies = [];

    public function __construct(
        private readonly OrganizationInterface $organizationInterface,
        private readonly MasterInterface $masterInterface,
    ) {}

    public function forOrganization(string $organizationId): Currency
    {
        return $this->currencies[$organizationId] ??= $this->masterInterface->currency(
            $this->organizationInterface->findOrganizationById($organizationId)->functional_currency_code,
        );
    }
}
