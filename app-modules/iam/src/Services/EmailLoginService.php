<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Lahatre\Iam\Data\LoginData;
use Lahatre\Iam\Data\SessionData;
use Lahatre\Iam\Exceptions\EmailAccountException;
use Lahatre\Iam\Exceptions\EmailLoginException;
use Lahatre\Iam\Jobs\SendLoginCode;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Notifications\EmailLoginCodeNotification;

final class EmailLoginService
{
    public const int EXPIRATION_MINUTES = 10;

    public const int MAX_ATTEMPTS = 5;

    public function __construct(private readonly EmailAccountService $accounts, private readonly AuthService $auth) {}

    /** Owns the transaction. A generic challenge ID never discloses whether an email is available. */
    public function requestCode(string $email): string
    {
        $id = (string) Str::uuid7();
        $key = 'email-login:'.hash('sha256', $email);

        return DB::transaction(function () use ($email, $id, $key): string {
            $user = $this->accounts->findForUpdate($email);
            $previous = DB::table('iam_email_login_challenges')->where('email', $email)->lockForUpdate()->first();
            if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($key.':cooldown', 1)) {
                return $previous->id ?? $id;
            }
            RateLimiter::hit($key, 3600);
            RateLimiter::hit($key.':cooldown', 60);
            $attempts = $previous && now()->lt($previous->expires_at) ? $previous->attempts : 0;
            $code = sprintf('%06d', random_int(0, 999999));
            DB::table('iam_email_login_challenges')->upsert([[
                'id'          => $id, 'email' => $email, 'code_hash' => $this->hashCode($id, $code),
                'attempts'    => $attempts, 'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
                'consumed_at' => null, 'created_at' => now(),
            ]], ['email'], ['id', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'created_at']);
            if (!$user?->trashed()) {
                SendLoginCode::dispatch($email, $id, $code)->afterCommit();
            }

            return $id;
        });
    }

    /**
     * Consume the code and issue a session atomically. Failed attempts commit
     * before the generic exception is raised, and resend retains their budget.
     * Email advisory locks serialize account creation with other onboarding flows.
     *
     * @throws EmailLoginException
     * @throws EmailAccountException
     *
     * @return array{user: User, token: string}
     */
    public function verify(LoginData $data, SessionData $session): array
    {
        $email = DB::table('iam_email_login_challenges')->where('id', $data->challengeId)->value('email');
        if (!is_string($email)) {
            throw EmailLoginException::invalidCode();
        }
        $result = DB::transaction(function () use ($data, $session, $email): ?array {
            $user = $this->accounts->findForUpdate($email);
            $challenge = DB::table('iam_email_login_challenges')->where('id', $data->challengeId)
                ->where('email', $email)->lockForUpdate()->first();
            if (!$challenge || $challenge->consumed_at !== null || now()->gte($challenge->expires_at)
                || $challenge->attempts >= self::MAX_ATTEMPTS || $user?->trashed()) {
                return null;
            }
            if (!hash_equals($challenge->code_hash, $this->hashCode($data->challengeId, $data->code))) {
                DB::table('iam_email_login_challenges')->where('id', $challenge->id)->where('email', $email)->increment('attempts');

                return null;
            }
            $user = $this->accounts->resolve($email, $user ? null : $data->firstName, $user ? null : $data->lastName);
            DB::table('iam_email_login_challenges')->where('id', $challenge->id)->where('email', $email)->update(['consumed_at' => now()]);

            return $this->auth->issueToken($user, $session);
        });
        if ($result === null) {
            throw EmailLoginException::invalidCode();
        }

        return $result;
    }

    /** Delivery never rotates a code; expired, replaced, exhausted and consumed jobs are skipped. */
    public function sendCode(string $email, string $challengeId, string $code): void
    {
        $exists = DB::table('iam_email_login_challenges')->where('email', $email)->where('id', $challengeId)
            ->where('code_hash', $this->hashCode($challengeId, $code))->whereNull('consumed_at')
            ->where('attempts', '<', self::MAX_ATTEMPTS)->where('expires_at', '>', now())->exists();
        if (!$exists || User::withTrashed()->where('email', $email)->first()?->trashed()) {
            return;
        }
        Notification::route('mail', $email)->notify(new EmailLoginCodeNotification($code));
    }

    private function hashCode(string $id, string $code): string
    {
        return hash_hmac('sha256', 'email-login:'.$id.':'.$code, config('app.key'));
    }
}
