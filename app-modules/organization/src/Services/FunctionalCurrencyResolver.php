<?php

declare(strict_types=1);

namespace Lahatre\Organization\Services;

use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Currency;
use Lahatre\Organization\Models\Organization;

/**
 * The functional currency of an organization never changes, so the lookup is
 * memoized for the request.
 */
final class FunctionalCurrencyResolver
{
    /** @var array<string, Currency> */
    private array $currencies = [];

    public function __construct(
        private readonly MasterInterface $masterInterface,
    ) {}

    public function forOrganization(string $organizationId): Currency
    {
        return $this->currencies[$organizationId] ??= $this->masterInterface->currency(
            Organization::query()->findOrFail($organizationId)->functional_currency_code,
        );
    }
}
