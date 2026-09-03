<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Catalog\Models\BundleStockOperation;

/** @mixin BundleStockOperation */
class BundleStockOperationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'bundle_id'            => $this->bundle_id,
            'type'                 => $this->type,
            'status'               => $this->status,
            'quantity'             => $this->quantity,
            'location_id'          => $this->location_id,
            'payload'              => $this->payload,
            'composition_snapshot' => $this->composition_snapshot,
            'out_transaction_id'   => $this->out_transaction_id,
            'in_transaction_id'    => $this->in_transaction_id,
            'completed_at'         => $this->completed_at,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'bundle'               => $this->whenLoaded(
                'bundle',
                fn (): BundleResource => BundleResource::make($this->bundle),
            ),
        ];
    }
}
