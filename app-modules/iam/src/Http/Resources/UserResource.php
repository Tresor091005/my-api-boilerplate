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

        $memberRoles = $user->organizationMemberships
            ->flatMap(fn ($membership) => $membership->memberRoles->map(fn ($memberRole): array => [
                'id'              => $memberRole->id,
                'member_id'       => $memberRole->member_id,
                'organization_id' => $memberRole->organization_id,
                'role_id'         => $memberRole->role_id,
                'role'            => $memberRole->role ? [
                    'id'          => $memberRole->role->id,
                    'name'        => $memberRole->role->name,
                    'description' => $memberRole->role->description,
                    'is_builtin'  => $memberRole->role->is_builtin,
                ] : null,
            ]))
            ->map(function (array $memberRole) use ($organizationService): array {
                $organization = $organizationService->findOrganizationById($memberRole['organization_id']);

                $memberRole['organization'] = $organization ? [
                    'id'   => $organization->id,
                    'name' => $organization->name,
                ] : null;

                return $memberRole;
            })
            ->values();

        return [
            'id'                     => $user->id,
            'first_name'             => $user->first_name,
            'last_name'              => $user->last_name,
            'email'                  => $user->email,
            'current_member_role_id' => $this->currentMemberRoleId,
            'member_roles'           => $memberRoles,
        ];
    }
}
