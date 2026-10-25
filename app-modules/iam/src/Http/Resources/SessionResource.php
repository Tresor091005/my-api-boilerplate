<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

/**
 * A sign-in session. The token secret and abilities are never shown.
 */
class SessionResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('is_current', columns: [], computedBy: fn (Model $session): bool => $session->getKey() === request()->user()?->currentAccessToken()?->getKey())
            ->field('created_at')
            ->field('last_used_at')
            ->field('expires_at')
            ->field('authentication_method', columns: ['metadata'], computedBy: fn (Model $session): mixed => self::meta($session, 'session.authentication_method'))
            ->field('last_request', columns: ['metadata'], computedBy: function (Model $session): ?array {
                $lastRequest = self::meta($session, 'session.last_request');

                return is_array($lastRequest) ? [
                    'at'       => $lastRequest['at'] ?? null,
                    'device'   => $lastRequest['device'] ?? null,
                    'location' => $lastRequest['location'] ?? null,
                ] : null;
            });
    }

    private static function meta(Model $session, string $key): mixed
    {
        return data_get($session->getAttribute('metadata'), $key);
    }
}
