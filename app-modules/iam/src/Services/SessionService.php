<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Data\SessionFilterData;
use Lahatre\Iam\Models\User;

final class SessionService
{
    public function paginate(User $user, SessionFilterData $filters): CursorPaginator
    {
        $query = $user->tokens()->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        $expiration = config('sanctum.expiration');
        if (is_numeric($expiration)) {
            $query->where('created_at', '>', now()->subMinutes((int) $expiration));
        }

        return stableCursorPaginate($query, $filters);
    }

    public function delete(User $user, int $sessionId): void
    {
        $user->tokens()->whereKey($sessionId)->firstOrFail()->delete();
    }

    public function deleteAll(User $user): void
    {
        $user->tokens()->delete();
    }

    /** Atomically replace only last-request evidence without overwriting context or other metadata. */
    public function recordActivity(PersonalAccessToken $token, SessionData $data): void
    {
        DB::update(
            "UPDATE personal_access_tokens SET metadata = jsonb_set(COALESCE(metadata, '{}'::jsonb), '{session}', COALESCE(metadata->'session', '{}'::jsonb) || ?::jsonb, true) WHERE id = ? AND tokenable_type = ? AND tokenable_id = ?",
            [json_encode(['last_request' => [...$data->toArray(), 'at' => now()->toISOString()]], JSON_THROW_ON_ERROR),
                $token->id, $token->tokenable_type, $token->tokenable_id],
        );
    }
}
