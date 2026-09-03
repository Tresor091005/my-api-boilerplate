<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Enums\BundleStockOperationStatus;
use Lahatre\Catalog\Enums\BundleStockOperationType;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Catalog\Models\BundleStockOperation;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/**
 * @extends Factory<BundleStockOperation>
 */
class BundleStockOperationFactory extends Factory
{
    use ResolvesOrganizationId;

    protected $model = BundleStockOperation::class;

    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();

        return [
            'organization_id'      => $organizationId,
            'bundle_id'            => Bundle::factory(),
            'type'                 => BundleStockOperationType::Attach,
            'status'               => BundleStockOperationStatus::Draft,
            'quantity'             => 1,
            'location_id'          => StockLocation::factory(),
            'payload'              => [],
            'composition_snapshot' => [],
            'out_transaction_id'   => null,
            'in_transaction_id'    => null,
            'completed_at'         => null,
        ];
    }
}
