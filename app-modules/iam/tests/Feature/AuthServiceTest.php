<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\AuthService;

uses(RefreshDatabase::class);

it('builds password reset links from the configured application URL', function (): void {
    config(['app.url' => 'https://api.example.test:8443']);
    $user = User::factory()->create();

    $link = app(AuthService::class)->forgotPassword($user->email);
    $queryString = parse_url($link, PHP_URL_QUERY);

    expect($link)->toStartWith('https://api.example.test:8443/auth/reset-password?');

    if (!is_string($queryString)) {
        $this->fail('The password reset link must contain a query string.');
    }

    parse_str($queryString, $query);

    expect($query)
        ->toHaveKey('email', $user->email)
        ->toHaveKey('token');
});
