<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Lahatre\Commitment\Exceptions\CommitmentException;
use Lahatre\Commitment\Models\GuestAccessChallenge;
use Lahatre\Commitment\Models\GuestAccessSession;
use Lahatre\Commitment\Models\ServiceCommitment;

final class GuestAccessService
{
    public function sendCode(string $reference): void
    {
        $code = (string) random_int(100000, 999999);
        DB::transaction(function () use ($reference, $code): void {
            $commitment = ServiceCommitment::query()->where('public_reference', $reference)
                ->lockForUpdate()->first();
            if ($commitment === null) {
                return;
            }
            GuestAccessChallenge::query()->where('organization_id', $commitment->organization_id)
                ->where('commitment_id', $commitment->id)
                ->whereNull('consumed_at')->update(['consumed_at' => now()]);
            GuestAccessChallenge::query()->create([
                'organization_id' => $commitment->organization_id,
                'commitment_id'   => $commitment->id,
                'code_hash'       => Hash::make($code),
                'attempts'        => 0,
                'expires_at'      => now()->addMinutes(10),
            ]);
            DB::afterCommit(function () use ($commitment, $code): void {
                Mail::raw(
                    __('commitment::notifications.guest_code_body', ['code' => $code]),
                    static function ($message) use ($commitment): void {
                        $message->to($commitment->client_email)
                            ->subject(__('commitment::notifications.guest_code_subject'));
                    },
                );
            });
        });
    }

    public function verifyCode(string $reference, string $code): string
    {
        $token = DB::transaction(function () use ($reference, $code): ?string {
            $commitment = ServiceCommitment::query()->where('public_reference', $reference)
                ->lockForUpdate()->first();
            if ($commitment === null) {
                throw CommitmentException::guestAccessDenied();
            }
            $challenge = GuestAccessChallenge::query()
                ->where('organization_id', $commitment->organization_id)
                ->where('commitment_id', $commitment->id)->whereNull('consumed_at')
                ->orderByDesc('created_at')->lockForUpdate()->first();
            if ($challenge === null || $challenge->expires_at->isPast() || $challenge->attempts >= 5) {
                throw CommitmentException::guestAccessDenied();
            }
            if (!Hash::check($code, $challenge->code_hash)) {
                $challenge->increment('attempts');

                return null;
            }
            $challenge->update(['consumed_at' => now()]);
            $token = Str::random(64);
            GuestAccessSession::query()->create([
                'organization_id' => $commitment->organization_id,
                'commitment_id'   => $commitment->id,
                'token_hash'      => hash('sha256', $token),
                'expires_at'      => now()->addHour(),
            ]);

            return $token;
        });
        if ($token === null) {
            throw CommitmentException::guestAccessDenied();
        }

        return $token;
    }

    public function authenticate(string $reference, ?string $token): ServiceCommitment
    {
        if ($token === null || $token === '') {
            throw CommitmentException::guestAccessDenied();
        }
        $session = GuestAccessSession::query()->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())->first();
        if ($session === null) {
            throw CommitmentException::guestAccessDenied();
        }

        $commitment = ServiceCommitment::query()->where('organization_id', $session->organization_id)
            ->whereKey($session->commitment_id)
            ->where('public_reference', $reference)->first();
        if ($commitment === null) {
            throw CommitmentException::guestAccessDenied();
        }

        return $commitment;
    }
}
