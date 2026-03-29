<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        return [
            'access_token' => $this->accessToken,
            'token_type'   => 'Bearer',
            'user'         => UserResource::make($this->resource),
        ];
    }
}
