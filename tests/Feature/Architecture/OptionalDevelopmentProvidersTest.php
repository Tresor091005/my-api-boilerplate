<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Symfony\Component\Process\Process;

it('registers Telescope when its development package is installed', function (): void {
    $providers = require base_path('bootstrap/providers.php');

    expect($providers)->toContain(TelescopeServiceProvider::class);
});

it('loads application providers without development packages', function (): void {
    $process = new Process([
        PHP_BINARY,
        '-n',
        '-r',
        'echo json_encode(require $argv[1], JSON_THROW_ON_ERROR);',
        base_path('bootstrap/providers.php'),
    ]);

    $process->mustRun();

    /** @var array<int, class-string> $providers */
    $providers = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

    expect($providers)
        ->toContain(AppServiceProvider::class)
        ->not->toContain(TelescopeServiceProvider::class);
});
