<?php

declare(strict_types=1);

namespace Lahatre\Organization\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Organization\Data\OrganizationSettingsData;
use Lahatre\Organization\Exceptions\OrganizationException;
use Lahatre\Organization\Models\Organization;
use Lahatre\Organization\Models\OrganizationSetting;

class OrganizationSettingsService
{
    public function __construct(
        private readonly MasterInterface $masterInterface,
    ) {}

    public function retrieve(): OrganizationSetting
    {
        $organization = $this->currentOrganization();
        /** @var OrganizationSetting $settings */
        $settings = $organization->settings()->firstOrFail();

        return $settings;
    }

    public function update(OrganizationSettingsData $data): OrganizationSetting
    {
        $organization = $this->currentOrganization();
        /** @var Collection<int, string> $currencyCodes */
        $currencyCodes = collect(array_map(
            fn (string $code): string => Str::toUpper($code),
            $data->enableCurrencies,
        ))
            ->unique()
            ->values();

        if (!$currencyCodes->contains($organization->functional_currency_code)) {
            throw OrganizationException::functionalCurrencyMustBeEnabled();
        }

        $currencies = $this->masterInterface->currencies($currencyCodes);

        foreach ($currencyCodes as $currencyCode) {
            if (!$currencies->has($currencyCode)) {
                throw OrganizationException::currencyNotFound($currencyCode);
            }
        }

        $settings = $this->retrieve();
        $settings->update(['enable_currencies' => $currencyCodes->all()]);

        return $settings->refresh();
    }

    private function currentOrganization(): Organization
    {
        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail(currentOrganizationId());

        return $organization;
    }
}
