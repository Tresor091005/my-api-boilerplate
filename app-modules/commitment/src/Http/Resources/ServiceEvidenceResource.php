<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Commitment\Models\ServiceEvidence;

/** @mixin ServiceEvidence */
final class ServiceEvidenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'deliverable_id'    => $this->deliverable_id,
            'version'           => $this->version,
            'title'             => $this->title,
            'description'       => $this->description,
            'outcome'           => $this->outcome,
            'narrative'         => $this->narrative,
            'reason'            => $this->reason,
            'scheduled_at'      => $this->scheduled_at,
            'performed_at'      => $this->performed_at,
            'quantity'          => $this->quantity,
            'display_unit_code' => $this->display_unit_code,
            'submitted_at'      => $this->submitted_at,
        ];
    }
}
