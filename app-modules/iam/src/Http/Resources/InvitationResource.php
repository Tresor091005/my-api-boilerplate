<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Models\Invitation;

/** @mixin Invitation */
class InvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'email'       => $this->email,
            'expires_at'  => $this->expires_at,
            'accepted_at' => $this->accepted_at,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
            'roles'       => $this->whenLoaded('roles', fn () => RoleResource::collection($this->roles)),
        ];
    }
}
