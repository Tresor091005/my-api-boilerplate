<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Models\User;

/**
 * The session issued after sign-in, with the account it belongs to. Built in code, so it stays
 * outside response selection.
 *
 * @mixin User
 */
class AuthResource extends JsonResource
{
    protected string $accessToken;

    /**
     * Set the access token for the resource.
     */
    public function withToken(string $token): self
    {
        $this->accessToken = $token;

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
        $values = UserResource::accountValues();

        // the auth services load the memberships; without them the key is left out, as before
        if (!$user->relationLoaded('organizationMemberships')) {
            unset($values['member_roles']);
        }

        return [
            'access_token' => $this->accessToken,
            'token_type'   => 'Bearer',
            'user'         => [
                ...array_map(fn (array $value): mixed => $value['read']($user), $values),
                // the token just issued has no member role, whatever this request's context holds
                'current_member_role_id' => null,
            ],
        ];
    }
}
