<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Contracts\OrganizationInterface;

class AuthResource extends JsonResource
{
    /** @var string */
    public $accessToken;

    /** @var string|null */
    public $currentMemberRoleId;

    /**
     * Set the access token for the resource.
     */
    public function withToken(string $token): self
    {
        $this->accessToken = $token;

        return $this;
    }

     /**
     * Set the access token for the resource.
     */
    public function withCurrentMemberRoleId(string|null $memberRoleId): self
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
                // TODO: cache organization fetches to avoid repeated cross-module lookups during auth serialization.
                $organization = $organizationService->findOrganizationById($memberRole['organization_id']);

                $memberRole['organization'] = $organization ? [
                    'id'   => $organization->id,
                    'name' => $organization->name,
                ] : null;

                return $memberRole;
            })
            ->values();

        return [
            'access_token' => $this->accessToken,
            'token_type'   => 'Bearer',
            'user'         => [
                'id'           => $user->id,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'email'        => $user->email,
                'currenct_member_role_id' => $this->currentMemberRoleId,
                'member_roles' => $memberRoles,
            ],
        ];
    }
}
