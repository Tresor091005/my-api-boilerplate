<?php

declare(strict_types=1);

namespace Lahatre\Sale\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Models\SaleSetting;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<SaleSetting>
 */
class SaleSettingFactory extends Factory
{
    use ResolvesOrganizationId;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => $this->resolveOrganizationId(),
            'default_mode'    => SaleMode::Counter,
        ];
    }
}
