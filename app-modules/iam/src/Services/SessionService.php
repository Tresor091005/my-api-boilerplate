<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Data\SessionFilterData;
use Lahatre\Iam\Integrations\SessionDeviceDetector;
use Lahatre\Iam\Integrations\SessionGeoLocator;
use Lahatre\Iam\Jobs\EnrichSession;
use Lahatre\Iam\Models\User;

final class SessionService
{
    public function __construct(private readonly SessionDeviceDetector $devices, private readonly SessionGeoLocator $locations) {}

    public function paginate(User $user, SessionFilterData $filters): CursorPaginator
    {
        $query = $this->unexpired($user->tokens()->getQuery());

        return stableCursorPaginate($query, $filters);
    }

    public function delete(User $user, string $sessionId): void
    {
        $user->tokens()->whereKey($sessionId)->firstOrFail()->delete();
    }

    public function deleteAll(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Atomically record activity and invalidate enrichment only when its inputs change.
     * The locked snapshot makes concurrent requests agree on whether to enqueue a job.
     */
    public function recordActivity(PersonalAccessToken $token, SessionData $data): void
    {
        $key = $data->fingerprint();
        $result = DB::selectOne(
            <<<'SQL'
                WITH previous AS (
                    SELECT id, metadata #>> '{session,enrichment_key}' AS enrichment_key,
                        COALESCE(metadata #> '{session,last_request}', '{}'::jsonb) AS last_request
                    FROM personal_access_tokens
                    WHERE id = ? AND tokenable_type = ? AND tokenable_id = ?
                    FOR UPDATE
                )
                UPDATE personal_access_tokens AS token
                SET metadata = jsonb_set(COALESCE(token.metadata, '{}'::jsonb), '{session}',
                    COALESCE(token.metadata->'session', '{}'::jsonb) || jsonb_build_object(
                        'enrichment_key', ?::text,
                        'last_request', (CASE WHEN previous.enrichment_key IS DISTINCT FROM ?::text
                            THEN previous.last_request || '{"device":null,"location":null}'::jsonb
                            ELSE previous.last_request END) || ?::jsonb
                    ), true)
                FROM previous WHERE token.id = previous.id
                RETURNING previous.enrichment_key IS DISTINCT FROM ?::text AS inputs_changed
                SQL,
            [$token->id, $token->tokenable_type, $token->tokenable_id, $key, $key,
                json_encode([...$data->toArray(), 'at' => now()->toISOString()], JSON_THROW_ON_ERROR), $key],
        );

        if ($result?->inputs_changed) {
            $this->queueEnrichment($token, $data);
        }
    }

    public function queueEnrichment(\Laravel\Sanctum\PersonalAccessToken $token, SessionData $data): void
    {
        EnrichSession::dispatch($token->getKey(), $token->tokenable_type, (string) $token->tokenable_id, $data);
    }

    /** Ignore revoked, expired, or superseded sessions, and merge results without replacing activity or organization metadata. */
    public function enrich(string $sessionId, string $tokenableType, string $tokenableId, SessionData $data): void
    {
        $key = $data->fingerprint();
        $session = $this->unexpired(PersonalAccessToken::query()->where('tokenable_type', $tokenableType)->where('tokenable_id', $tokenableId))
            ->whereKey($sessionId)->where('metadata->session->enrichment_key', $key);
        if (!$session->exists()) {
            return;
        }

        $enrichment = ['device' => $this->devices->detect($data->userAgent), 'location' => $this->locations->locate($data->ipAddress)];
        $expiration = config('sanctum.expiration');
        $cutoff = is_numeric($expiration) ? now()->subMinutes((int) $expiration)->toISOString() : null;
        DB::update(
            <<<'SQL'
                UPDATE personal_access_tokens
                SET metadata = jsonb_set(metadata, '{session,last_request}',
                    COALESCE(metadata #> '{session,last_request}', '{}'::jsonb) || ?::jsonb, true)
                WHERE id = ? AND tokenable_type = ? AND tokenable_id = ?
                    AND metadata #>> '{session,enrichment_key}' = ?
                    AND (expires_at IS NULL OR expires_at > ?::timestamptz)
                    AND (?::timestamptz IS NULL OR created_at > ?::timestamptz)
                SQL,
            [json_encode($enrichment, JSON_THROW_ON_ERROR), $sessionId, $tokenableType, $tokenableId, $key, now()->toISOString(), $cutoff, $cutoff],
        );
    }

    private function unexpired(Builder $query): Builder
    {
        $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        $expiration = config('sanctum.expiration');
        if (is_numeric($expiration)) {
            $query->where('created_at', '>', now()->subMinutes((int) $expiration));
        }

        return $query;
    }
}
