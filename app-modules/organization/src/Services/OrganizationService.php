<?php

declare(strict_types=1);

namespace Lahatre\Organization\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Models\Currency;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Organization\Data\OrganizationData;
use Lahatre\Organization\Enums\ExchangeRateContext;
use Lahatre\Organization\Exceptions\OrganizationException;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;

class OrganizationService implements OrganizationInterface
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService,
        protected MasterInterface $masterInterface,
        protected FunctionalCurrencyResolver $functionalCurrencyResolver,
    ) {}

    public function initializeOrganization(OrganizationData $data): Organization
    {
        if (!$this->masterInterface->currencies(collect([$data->currencyCode]))->has($data->currencyCode)) {
            throw OrganizationException::currencyNotFound($data->currencyCode);
        }

        return DB::transaction(function () use ($data): Organization {
            $organization = Organization::query()->create([
                'owner_id'                 => $data->ownerId,
                'name'                     => $data->name,
                'functional_currency_code' => $data->currencyCode,
            ]);
            $organization->settings()->create([
                'enable_currencies' => [$data->currencyCode],
                'timezone'          => $data->timezone,
            ]);

            return $organization->load('settings');
        });
    }

    public function findOrganizationById(string $organizationId): Organization
    {
        return Organization::query()->findOrFail($organizationId);
    }

    public function functionalCurrency(string $organizationId): Currency
    {
        return $this->functionalCurrencyResolver->forOrganization($organizationId);
    }

    public function today(string $organizationId): CarbonImmutable
    {
        $timezone = OrganizationSetting::query()
            ->where('organization_id', $organizationId)
            ->value('timezone');

        return CarbonImmutable::now(is_string($timezone) ? $timezone : (string) config('app.timezone'))->startOfDay();
    }

    public function isCurrencyEnabled(string $organizationId, string $currencyCode): bool
    {
        if ($this->functionalCurrency($organizationId)->code === $currencyCode) {
            return true;
        }

        $enabledCurrencies = OrganizationSetting::query()
            ->where('organization_id', $organizationId)
            ->value('enable_currencies');

        return is_array($enabledCurrencies) && in_array($currencyCode, $enabledCurrencies, true);
    }

    public function getLibraryQuotaBytes(string $organizationId): ?int
    {
        $settings = OrganizationSetting::query()
            ->where('organization_id', $organizationId)
            ->firstOrFail();

        return $settings->storage_quota_bytes === null
            ? null
            : (int) $settings->storage_quota_bytes;
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
