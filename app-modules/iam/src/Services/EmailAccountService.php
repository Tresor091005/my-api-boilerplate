<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Support\Facades\DB;
use Lahatre\Iam\Exceptions\EmailAccountException;
use Lahatre\Iam\Models\User;

/** Transactional support shared by email ownership onboarding flows. */
final class EmailAccountService
{
    /**
     * The caller owns the transaction. The email lock also protects absent users
     * from concurrent account creation through registration or another invitation.
     */
    public function findForUpdate(string $email): ?User
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['iam-email:'.$email]);

        return User::withTrashed()->where('email', $email)->lockForUpdate()->first();
    }

    /**
     * Resolve the verified email owner within the caller's transaction.
     *
     * @throws EmailAccountException
     */
    public function resolve(string $email, ?string $firstName, ?string $lastName, ?string $password): User
    {
        $user = $this->findForUpdate($email);
        if ($user?->trashed()) {
            throw EmailAccountException::unavailableEmail();
        }
        if (!$user && (!$firstName || !$lastName || !$password)) {
            throw EmailAccountException::userDetailsRequired();
        }
        if ($user && ($firstName !== null || $lastName !== null || $password !== null)) {
            throw EmailAccountException::userDetailsForbidden();
        }
        if (!$user) {
            $user = User::query()->create([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'password'   => $password,
            ]);
        }
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
            $user->save();
        }

        return $user;
    }
}
