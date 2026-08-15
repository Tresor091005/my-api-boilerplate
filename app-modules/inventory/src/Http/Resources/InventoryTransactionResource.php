<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Inventory\Models\InventoryTransaction;
use Lahatre\Shared\Http\Resources\Concerns\RendersResponseIncludes;

/**
 * @mixin InventoryTransaction
 */
class InventoryTransactionResource extends JsonResource
{
    use RendersResponseIncludes;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'idempotency_key'            => $this->idempotency_key,
            'reference_type'             => $this->reference_type,
            'reference_id'               => $this->reference_id,
            'transaction_type'           => $this->transaction_type,
            'metadata'                   => $this->metadata,
            'reversal_of_transaction_id' => $this->reversal_of_transaction_id,
            'created_at'                 => $this->created_at,
            'updated_at'                 => $this->updated_at,
            'movements'                  => $this->includeWhenRequestedAndLoaded(
                include: 'movements',
                relation: 'movements',
                resolver: fn ($movements): mixed => InventoryMovementResource::collection($movements),
            ),
        ];
    }
}
