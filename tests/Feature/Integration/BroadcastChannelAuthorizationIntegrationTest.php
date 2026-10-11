<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Lahatre\Iam\Models\User;

uses(RefreshDatabase::class);

/*
 * phpunit.xml selects the null broadcaster, whose auth() returns an empty
 * success for every request without running channel callbacks. These tests
 * switch to the Reverb (Pusher protocol) broadcaster with local credentials
 * and register routes/channels.php on it, so /broadcasting/auth runs the real
 * channel callback and signs the answer locally, without any network call.
 */
beforeEach(function (): void {
    config([
        'broadcasting.default'                   => 'reverb',
        'broadcasting.connections.reverb.key'    => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    require base_path('routes/channels.php');
});

/**
 * Request a private-channel signature the way Laravel Echo does.
 */
function authorizeUserChannel(User $channelOwner): TestResponse
{
    return currentTestCase()->postJson('/broadcasting/auth', [
        'socket_id'    => '1234.5678',
        'channel_name' => 'private-'.$channelOwner->broadcastChannel(),
    ]);
}

it('authorizes a bearer-authenticated user on their own private user channel', function (): void {
    $user = User::factory()->create();

    expect($user->broadcastChannel())->toBe("Lahatre.Iam.Models.User.{$user->id}");

    $this->withToken($user->createToken('broadcast-test')->plainTextToken);

    $response = authorizeUserChannel($user)->assertOk();

    expect($response->json('auth'))->toStartWith('test-key:');
});

it('refuses a user on another user private channel even when integer casts of their UUIDs collide', function (): void {
    // Both UUIDs cast to the integer 0, which the previous callback treated as equal.
    $user = User::factory()->create(['id' => 'a0000000-0000-4000-8000-000000000001']);
    $otherUser = User::factory()->create(['id' => 'b0000000-0000-4000-8000-000000000002']);

    $this->withToken($user->createToken('broadcast-test')->plainTextToken);

    authorizeUserChannel($otherUser)->assertForbidden();
});

it('refuses an unauthenticated request on a private user channel', function (): void {
    authorizeUserChannel(User::factory()->create())->assertForbidden();
});
