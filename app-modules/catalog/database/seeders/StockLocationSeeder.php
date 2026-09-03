<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Data\StockLocationData;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Services\StockLocationService;

final class StockLocationSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(StockLocationService::class);
        $organizationId = currentOrganizationId();

        $locations = [
            [
                'name'      => 'Main Warehouse',
                'is_active' => true,
                'address'   => ['line' => '1 Industrial Road', 'city' => 'Cotonou', 'country' => 'BJ'],
            ],
            [
                'name'      => 'Reserve Warehouse',
                'is_active' => false,
                'address'   => ['line' => '4 Logistics Road', 'city' => 'Abomey-Calavi', 'country' => 'BJ'],
            ],
        ];

        foreach ($locations as $locationData) {
            $location = StockLocation::query()
                ->where('organization_id', $organizationId)
                ->where('name', $locationData['name'])
                ->first();

            if (!$location instanceof StockLocation) {
                $service->create(StockLocationData::fromArray([
                    'name'      => $locationData['name'],
                    'is_active' => $locationData['is_active'],
                    'address'   => $locationData['address'],
                ]));

                continue;
            }

            $service->update($location, StockLocationData::fromArray([
                'name'      => $locationData['name'],
                'is_active' => $locationData['is_active'],
                'address'   => $locationData['address'],
            ]));
        }
    }
}
