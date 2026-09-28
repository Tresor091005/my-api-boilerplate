<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Commitment\Models\ServiceDeliverable;

/** @mixin ServiceDeliverable */
final class ServiceDeliverableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'commitment_id'        => $this->commitment_id,
            'title'                => $this->title,
            'description'          => $this->description,
            'scheduled_at'         => $this->scheduled_at,
            'performed_at'         => $this->performed_at,
            'quantity'             => $this->quantity,
            'display_unit_code'    => $this->display_unit_code,
            'execution_state'      => $this->execution_state,
            'validation_state'     => $this->validation_state,
            'accepted_evidence_id' => $this->accepted_evidence_id,
            'current_evidence_id'  => $this->current_evidence_id,
        ];
    }
}
