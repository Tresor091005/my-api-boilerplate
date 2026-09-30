<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Models\OrganizationMember;

/** @mixin OrganizationMember */
class OrganizationMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'is_active'    => $this->is_active,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'user'         => $this->whenLoaded('user', fn () => UserProfileResource::make($this->user)),
            'member_roles' => $this->whenLoaded('memberRoles', fn () => MemberRoleResource::collection($this->memberRoles)),
        ];
    }
}
