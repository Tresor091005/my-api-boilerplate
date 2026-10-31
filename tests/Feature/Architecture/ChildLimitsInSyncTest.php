<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('enforces in the database every limit declared in config/child-limits.php', function (): void {
    expect(Artisan::call('child-limits:sync', ['--check' => true]))->toBe(0, Artisan::output());
});
