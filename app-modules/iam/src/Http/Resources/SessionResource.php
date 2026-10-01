<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lahatre\Iam\Auth\PersonalAccessToken;

/** @mixin PersonalAccessToken */
class SessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'is_current'            => $this->id === $request->user()?->currentAccessToken()?->getKey(),
            'created_at'            => $this->created_at,
            'last_used_at'          => $this->last_used_at,
            'expires_at'            => $this->expires_at,
            'authentication_method' => $this->getMeta('session.authentication_method'),
            'last_request'          => $this->getMeta('session.last_request'),
        ];
    }
}
