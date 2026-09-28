<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Commitment\Models\ServiceReview;

/** @mixin ServiceReview */
final class ServiceReviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'subject_type' => $this->subject_type,
            'subject_id'   => $this->subject_id,
            'decision'     => $this->decision,
            'comment'      => $this->comment,
            'batch_id'     => $this->batch_id,
            'actor_email'  => $this->actor_email,
            'created_at'   => $this->created_at,
        ];
    }
}
