<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\StockTransfer;

/** @mixin StockTransfer */
class StockTransferResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'source_location_id'       => $this->source_location_id,
            'destination_location_id'  => $this->destination_location_id,
            'status'                   => $this->status,
            'inventory_transaction_id' => $this->inventory_transaction_id,
            'reversal_transaction_id'  => $this->reversal_transaction_id,
            'completed_at'             => $this->completed_at,
            'cancelled_at'             => $this->cancelled_at,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
            'lines'                    => StockTransferLineResource::collection($this->whenLoaded('lines')),
            'source_location'          => $this->whenLoaded('sourceLocation', fn (): StockLocationResource => StockLocationResource::make($this->sourceLocation)),
            'destination_location'     => $this->whenLoaded('destinationLocation', fn (): StockLocationResource => StockLocationResource::make($this->destinationLocation)),
        ];
    }
}
