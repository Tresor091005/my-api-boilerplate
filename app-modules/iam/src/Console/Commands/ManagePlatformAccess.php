<?php

declare(strict_types=1);

namespace Lahatre\Iam\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\PlatformAccessService;

final class ManagePlatformAccess extends Command
{
    protected $signature = 'iam:platform-access
        {email : Email of the user}
        {--revoke : Revoke platform access instead of granting it}';

    protected $description;

    public function __construct(private readonly PlatformAccessService $platformAccess)
    {
        parent::__construct();
        $this->description = __('iam::console.platform_access.description');
    }

    public function handle(): int
    {
        $email = Str::normalize((string) $this->argument('email'));
        $revoke = (bool) $this->option('revoke');
        $user = User::query()->when($revoke, fn ($query) => $query->withTrashed())->where('email', $email)->first();

        if ($user === null) {
            $this->error(__('iam::console.platform_access.user_not_found', ['email' => $email]));

            return self::FAILURE;
        }

        if ($revoke) {
            $this->info($this->platformAccess->revoke($user)
                ? __('iam::console.platform_access.revoked', ['email' => $email])
                : __('iam::console.platform_access.not_granted', ['email' => $email]));

            return self::SUCCESS;
        }

        $this->info($this->platformAccess->grant($user)
            ? __('iam::console.platform_access.granted', ['email' => $email])
            : __('iam::console.platform_access.already_granted', ['email' => $email]));

        return self::SUCCESS;
    }
}
