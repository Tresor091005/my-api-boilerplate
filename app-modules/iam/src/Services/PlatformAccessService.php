<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Lahatre\Iam\Models\PlatformUser;
use Lahatre\Iam\Models\User;

final class PlatformAccessService
{
    public function isPlatformUser(User $user): bool
    {
        return PlatformUser::query()->where('user_id', $user->id)->exists();
    }

    /** Return false when the user already had platform access. */
    public function grant(User $user): bool
    {
        return PlatformUser::query()->createOrFirst(['user_id' => $user->id])->wasRecentlyCreated;
    }

    /** Return false when the user had no platform access. */
    public function revoke(User $user): bool
    {
        return PlatformUser::query()->where('user_id', $user->id)->delete() > 0;
    }
}
