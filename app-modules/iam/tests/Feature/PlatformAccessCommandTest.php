<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Lahatre\Iam\Models\PlatformUser;
use Lahatre\Iam\Models\User;

uses(RefreshDatabase::class);

/** @param  array<string, mixed>  $parameters */
function platformAccessCommand(array $parameters, int $exitCode, string $outputKey, string $email): void
{
    expect(Artisan::call('iam:platform-access', $parameters))->toBe($exitCode)
        ->and(trim(Artisan::output()))->toBe(__($outputKey, ['email' => $email]));
}

it('grants platform access by email once', function (): void {
    $user = User::factory()->create(['email' => 'operator@example.com']);

    platformAccessCommand(['email' => ' Operator@Example.com '], Command::SUCCESS, 'iam::console.platform_access.granted', 'operator@example.com');
    platformAccessCommand(['email' => 'operator@example.com'], Command::SUCCESS, 'iam::console.platform_access.already_granted', 'operator@example.com');

    expect(PlatformUser::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('revokes platform access by email', function (): void {
    $user = User::factory()->create(['email' => 'operator@example.com']);
    PlatformUser::factory()->create(['user_id' => $user->id]);

    platformAccessCommand(['email' => 'operator@example.com', '--revoke' => true], Command::SUCCESS, 'iam::console.platform_access.revoked', 'operator@example.com');
    platformAccessCommand(['email' => 'operator@example.com', '--revoke' => true], Command::SUCCESS, 'iam::console.platform_access.not_granted', 'operator@example.com');

    expect(PlatformUser::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('fails for an unknown email without granting anything', function (): void {
    platformAccessCommand(['email' => 'nobody@example.com'], Command::FAILURE, 'iam::console.platform_access.user_not_found', 'nobody@example.com');

    expect(PlatformUser::query()->count())->toBe(0);
});
