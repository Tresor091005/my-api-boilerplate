<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Http\Resources\CurrencyResource;
use Lahatre\Master\Http\Resources\UnitResource;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/**
 * @mixin InventoryStock
 */
class InventoryStockResource extends JsonResource
{
    use RendersResponseIncludes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'item_id'           => $this->item_id,
            'location_id'       => $this->location_id,
            'unit_cost'         => $this->resolveUnitCost(),
            'total_cost'        => $this->resolveTotalCost(),
            'cost_remainder'    => $this->resolveCostRemainder(),
            'currency_code'     => $this->currency_code,
            'quantity'          => $this->quantity,
            'remaining'         => $this->remaining,
            'unit_code'         => $this->unit_code,
            'expiration_date'   => $this->expiration_date,
            'metadata'          => $this->metadata,
            'exchange_metadata' => $this->exchange_metadata,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'location'          => $this->includeWhenRequestedAndLoaded(
                include: ['location', 'stocks.location'],
                relation: 'location',
                resolver: fn ($location): mixed => InventoryLocationResource::make($location),
            ),
            'unit' => $this->includeWhenRequestedAndLoaded(
                include: ['unit', 'stocks.unit'],
                relation: 'unit',
                resolver: fn ($unit): mixed => UnitResource::make($unit),
            ),
            'currency' => $this->includeWhenRequestedAndLoaded(
                include: ['currency', 'stocks.currency'],
                relation: 'currency',
                resolver: fn ($currency): mixed => CurrencyResource::make($currency),
            ),
            'movements' => $this->includeWhenRequestedAndLoaded(
                include: ['movements', 'stocks.movements'],
                relation: 'movements',
                resolver: fn ($movements): mixed => InventoryMovementResource::collection($movements),
            ),
        ];
    }

    private function resolveUnitCost(): string|int
    {
        if (!$this->currency_code) {
            return $this->unit_cost;
        }

        return app(MasterInterface::class)->fromMinor((string) $this->unit_cost, $this->currency_code);
    }

    private function resolveTotalCost(): string|int
    {
        $totalCost = ($this->remaining * $this->unit_cost) + $this->cost_remainder;

        if (!$this->currency_code) {
            return $totalCost;
        }

        return app(MasterInterface::class)->fromMinor((string) $totalCost, $this->currency_code);
    }

    private function resolveCostRemainder(): string|int
    {
        if (!$this->currency_code) {
            return $this->cost_remainder;
        }

        return app(MasterInterface::class)->fromMinor((string) $this->cost_remainder, $this->currency_code);
    }
}
