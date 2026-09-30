<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Models\MemberRole;

/** @mixin MemberRole */
class MemberRoleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'role_id'    => $this->role_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'role'       => $this->whenLoaded('role', fn () => RoleResource::make($this->role)),
        ];
    }
}
