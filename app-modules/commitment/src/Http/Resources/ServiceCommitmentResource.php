<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Commitment\Models\ServiceCommitment;

/** @mixin ServiceCommitment */
final class ServiceCommitmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                           => $this->id,
            'service_id'                   => $this->service_id,
            'customer_id'                  => $this->customer_id,
            'public_reference'             => $this->public_reference,
            'state'                        => $this->state,
            'accepted_proposal_id'         => $this->accepted_proposal_id,
            'pending_lifecycle_request_id' => $this->pending_lifecycle_request_id,
            'created_at'                   => $this->created_at,
            'updated_at'                   => $this->updated_at,
        ];
    }
}
