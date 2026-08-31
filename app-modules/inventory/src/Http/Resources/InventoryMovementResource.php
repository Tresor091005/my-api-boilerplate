<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Master\Http\Resources\CurrencyResource;
use Lahatre\Master\Http\Resources\UnitResource;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/**
 * @mixin InventoryMovement
 */
class InventoryMovementResource extends JsonResource
{
    use RendersResponseIncludes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'movement_type'           => $this->movement_type,
            'transaction_id'          => $this->transaction_id,
            'link_id'                 => $this->link_id,
            'item_id'                 => $this->item_id,
            'stock_id'                => $this->stock_id,
            'location_id'             => $this->location_id,
            'quantity'                => $this->quantity,
            'base_unit_code'          => $this->base_unit_code,
            'total_cost'              => $this->resolveTotalCost(),
            'currency_code'           => $this->currency_code,
            'expiration_date'         => $this->expiration_date?->toDateString(),
            'metadata'                => $this->metadata,
            'exchange_metadata'       => $this->exchange_metadata,
            'stock_metadata_snapshot' => $this->stock_metadata_snapshot,
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
            'location'                => $this->includeWhenRequestedAndLoaded(
                include: ['location', 'movements.location'],
                relation: 'location',
                resolver: fn ($location): mixed => InventoryLocationResource::make($location),
            ),
            'stock' => $this->includeWhenRequestedAndLoaded(
                include: ['stock', 'movements.stock'],
                relation: 'stock',
                resolver: fn ($stock): mixed => InventoryStockResource::make($stock),
            ),
            'unit' => $this->includeWhenRequestedAndLoaded(
                include: ['unit', 'movements.unit'],
                relation: 'unit',
                resolver: fn ($unit): mixed => UnitResource::make($unit),
            ),
            'currency' => $this->includeWhenRequestedAndLoaded(
                include: ['currency', 'movements.currency'],
                relation: 'currency',
                resolver: fn ($currency): mixed => CurrencyResource::make($currency),
            ),
        ];
    }

    private function resolveTotalCost(): string|int
    {
        if (!$this->currency_code) {
            return $this->total_cost;
        }

        return app(MasterInterface::class)->fromMinor((string) $this->total_cost, $this->currency_code);
    }
}
