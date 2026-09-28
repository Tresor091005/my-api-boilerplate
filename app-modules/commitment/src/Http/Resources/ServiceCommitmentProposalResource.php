<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Commitment\Models\ServiceCommitmentProposal;

/** @mixin ServiceCommitmentProposal */
final class ServiceCommitmentProposalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'commitment_id' => $this->commitment_id,
            'version'       => $this->version,
            'title'         => $this->title,
            'terms'         => $this->terms,
            'state'         => $this->state,
            'submitted_at'  => $this->submitted_at,
            'reviewed_at'   => $this->reviewed_at,
        ];
    }
}
