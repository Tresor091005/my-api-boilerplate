<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Contracts\OrganizationInterface;

class UserResource extends JsonResource
{
    protected ?string $currentMemberRoleId = null;

    /**
     * Set the current member role ID.
     */
    public function withCurrentMemberRoleId(?string $memberRoleId): self
    {
        $this->currentMemberRoleId = $memberRoleId;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        /** @var OrganizationInterface $organizationService */
        $organizationService = app(OrganizationInterface::class);

        return [
            'id'                     => $user->id,
            'first_name'             => $user->first_name,
            'last_name'              => $user->last_name,
            'email'                  => $user->email,
            'current_member_role_id' => $this->currentMemberRoleId,
            'member_roles'           => $this->whenLoaded(
                'organizationMemberships',
                function ($memberships) use ($organizationService): array {
                    $memberRoles = [];

                    foreach ($memberships as $membership) {
                        if (!$membership->relationLoaded('memberRoles')) {
                            continue;
                        }

                        $organization = $organizationService->findOrganizationById($membership->organization_id);

                        foreach ($membership->memberRoles as $memberRole) {
                            if (!$memberRole->relationLoaded('role')) {
                                continue;
                            }

                            $memberRoles[] = [
                                'id'              => $memberRole->id,
                                'member_id'       => $memberRole->member_id,
                                'organization_id' => $memberRole->organization_id,
                                'role_id'         => $memberRole->role_id,
                                'role'            => [
                                    'id'          => $memberRole->role->id,
                                    'name'        => $memberRole->role->name,
                                    'description' => $memberRole->role->description,
                                    'is_builtin'  => $memberRole->role->is_builtin,
                                ],
                                'organization' => [
                                    'id'   => $organization->id,
                                    'name' => $organization->name,
                                ],
                            ];
                        }
                    }

                    return $memberRoles;
                },
            ),
        ];
    }
}
