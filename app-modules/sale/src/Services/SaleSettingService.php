<?php

declare(strict_types=1);

namespace Lahatre\Sale\Services;

use Illuminate\Support\Str;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Models\SaleSetting;
use Lahatre\Sale\ViewData\SaleSettingsViewData;

final readonly class SaleSettingService
{
    public function retrieve(): SaleSettingsViewData
    {
        return new SaleSettingsViewData($this->defaultMode(currentOrganizationId()));
    }

    /** One upsert, so two first writes at once cannot both insert. */
    public function update(SaleMode $defaultMode): SaleSettingsViewData
    {
        $now = now();

        SaleSetting::query()->upsert([[
            'id'              => (string) Str::uuid7(),
            'organization_id' => currentOrganizationId(),
            'default_mode'    => $defaultMode->value,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]], ['organization_id'], ['default_mode', 'updated_at']);

        return new SaleSettingsViewData($defaultMode);
    }

    public function defaultMode(string $organizationId): SaleMode
    {
        $defaultMode = SaleSetting::query()
            ->where('organization_id', $organizationId)
            ->value('default_mode');

        return $defaultMode instanceof SaleMode ? $defaultMode : SaleMode::Counter;
    }
}
