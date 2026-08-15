<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

it('lists project helpers in a successful table response', function (): void {
    expect(Artisan::all())->toHaveKey('helpers:list');

    $exitCode = Artisan::call('helpers:list');
    $output = Artisan::output();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($output)->toContain('| Helper')
        ->and($output)->toContain('| Description')
        ->and($output)->toContain('| File')
        ->and($output)->toContain('| Line');
});
