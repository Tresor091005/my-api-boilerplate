<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Iam\Auth\PersonalAccessToken;
use Lahatre\Iam\Data\GoogleAuthenticationData;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Exceptions\GoogleAuthException;
use Lahatre\Iam\Integrations\GoogleIdentityVerifier;
use Lahatre\Iam\Models\ExternalIdentity;
use Lahatre\Iam\Models\User;
use stdClass;

/**
 * @phpstan-type GoogleIdentity array{issuer: string, subject: string, email: string, authoritative_email: bool, first_name: ?string, last_name: ?string, nonce: string}
 */
final class GoogleAuthService
{
    public const int EXPIRATION_MINUTES = 10;

    public function __construct(
        private readonly GoogleIdentityVerifier $verifier,
        private readonly EmailAccountService $accounts,
        private readonly AuthService $auth,
    ) {}

    /** @return array{challenge_id: string, nonce: string, expires_at: string} */
    public function createChallenge(): array
    {
        if (!config('services.google.client_id')) {
            throw GoogleAuthException::configurationMissing();
        }
        $id = (string) Str::uuid7();
        $nonce = Str::random(64);
        $expiresAt = now()->addMinutes(self::EXPIRATION_MINUTES);
        DB::table('iam_google_auth_challenges')->insert([
            'id' => $id, 'nonce_hash' => hash('sha256', $nonce), 'expires_at' => $expiresAt, 'created_at' => now(),
        ]);

        return ['challenge_id' => $id, 'nonce' => $nonce, 'expires_at' => $expiresAt->toISOString()];
    }

    /**
     * Owns the transaction. Pending results create neither a user nor a session.
     * Existing email accounts and non-authoritative Google emails use the existing OTP flow before linking.
     *
     * @return array{user: User, token: string}|array{status: string, challenge_id: string, email: string}
     */
    public function authenticate(GoogleAuthenticationData $data, SessionData $session): array
    {
        $identity = $this->verifier->verify($data->credential);

        return DB::transaction(function () use ($data, $session, $identity): array {
            $challenge = $this->lockChallenge($data->challengeId, $identity);
            $external = $this->lockIdentity($identity);
            if ($external !== null) {
                $user = User::query()->whereKey($external->user_id)->lockForUpdate()->first();
                if ($user === null) {
                    throw GoogleAuthException::identityUnavailable();
                }
                $this->consumeChallenge($challenge->id);

                return $this->auth->issueToken($user, $session, 'google');
            }
            $user = $this->accounts->findForUpdate($identity['email']);
            if ($user?->trashed()) {
                throw GoogleAuthException::identityUnavailable();
            }
            $this->bindChallenge($challenge->id, $identity);
            if ($user !== null || !$identity['authoritative_email']) {
                return ['status' => 'email_verification_required', 'challenge_id' => $challenge->id, 'email' => $identity['email']];
            }
            $firstName = $data->firstName ?? $identity['first_name'];
            $lastName = $data->lastName ?? $identity['last_name'];
            $user = $this->accounts->resolve($identity['email'], $firstName, $lastName);
            $this->attachIdentity($user, $identity);
            $this->consumeChallenge($challenge->id);

            return $this->auth->issueToken($user, $session, 'google');
        });
    }

    /** Owns the transaction. Link only the current account after a recent email OTP login; keep its session. */
    public function link(User $actor, GoogleAuthenticationData $data): void
    {
        $identity = $this->verifier->verify($data->credential);
        DB::transaction(function () use ($actor, $data, $identity): void {
            $challenge = $this->lockChallenge($data->challengeId, $identity);
            $external = $this->lockIdentity($identity);
            $user = $this->accounts->findForUpdate($identity['email']);
            if ($user === null || $user->trashed() || $user->id !== $actor->id
                || ($external !== null && $external->user_id !== $user->id)) {
                throw GoogleAuthException::identityUnavailable();
            }
            $current = $actor->currentAccessToken();
            $token = $current instanceof PersonalAccessToken
                ? PersonalAccessToken::query()->whereKey($current->id)->where('tokenable_id', $actor->id)
                    ->where('tokenable_type', $actor->getMorphClass())->lockForUpdate()->first()
                : null;
            if ($token === null || $token->expires_at?->isPast()
                || $token->created_at === null || $token->created_at->lt(now()->subMinutes(self::EXPIRATION_MINUTES))
                || $token->getMeta('session.authentication_method') !== 'email_otp'
                || $user->email_verified_at === null) {
                throw GoogleAuthException::recentEmailAuthenticationRequired();
            }
            $this->attachIdentity($user, $identity);
            $this->consumeChallenge($challenge->id);
        });
    }

    /** @param GoogleIdentity $identity */
    private function lockChallenge(string $id, array $identity): stdClass
    {
        $challenge = DB::table('iam_google_auth_challenges')->where('id', $id)->lockForUpdate()->first();
        if ($challenge === null || $challenge->consumed_at !== null || now()->gte($challenge->expires_at)
            || !hash_equals($challenge->nonce_hash, hash('sha256', $identity['nonce']))) {
            throw GoogleAuthException::invalidChallenge();
        }
        if ($challenge->identity !== null) {
            $bound = json_decode($challenge->identity, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($bound) || ($bound['issuer'] ?? null) !== $identity['issuer']
                || ($bound['subject'] ?? null) !== $identity['subject'] || ($bound['email'] ?? null) !== $identity['email']) {
                throw GoogleAuthException::invalidChallenge();
            }
        }

        return $challenge;
    }

    /** Serialize concurrent attempts, including creation when no external identity row exists yet. @param GoogleIdentity $identity */
    private function lockIdentity(array $identity): ?ExternalIdentity
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['iam-google:'.$identity['issuer'].':'.$identity['subject']]);

        return ExternalIdentity::query()->where('provider', 'google')
            ->where('issuer', $identity['issuer'])->where('subject', $identity['subject'])->lockForUpdate()->first();
    }

    /** Caller holds the identity and email locks. @param GoogleIdentity $identity */
    private function attachIdentity(User $user, array $identity): void
    {
        $existing = ExternalIdentity::query()->where('user_id', $user->id)->where('provider', 'google')->first();
        if ($existing !== null) {
            if ($existing->issuer !== $identity['issuer'] || $existing->subject !== $identity['subject']) {
                throw GoogleAuthException::identityUnavailable();
            }

            return;
        }
        ExternalIdentity::query()->create([
            'user_id' => $user->id, 'provider' => 'google', 'issuer' => $identity['issuer'], 'subject' => $identity['subject'],
        ]);
    }

    /** @param GoogleIdentity $identity */
    private function bindChallenge(string $id, array $identity): void
    {
        DB::table('iam_google_auth_challenges')->where('id', $id)->update([
            'identity' => json_encode($this->identityBinding($identity), JSON_THROW_ON_ERROR),
        ]);
    }

    /** @param GoogleIdentity $identity @return array{issuer: string, subject: string, email: string} */
    private function identityBinding(array $identity): array
    {
        return ['issuer' => $identity['issuer'], 'subject' => $identity['subject'], 'email' => $identity['email']];
    }

    private function consumeChallenge(string $id): void
    {
        DB::table('iam_google_auth_challenges')->where('id', $id)->update(['consumed_at' => now()]);
    }
}
