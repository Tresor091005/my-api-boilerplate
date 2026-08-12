<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('does not throw bare base exceptions in module source code', function (): void {
    $finder = new Finder;

    $finder->in(base_path('app-modules'))
        ->path('#/src/#')
        ->name('*.php')
        ->files();

    $failures = [];

    foreach ($finder as $file) {
        $content = $file->getContents();
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            if (preg_match('/throw\s+new\s+\\\\?Exception\s*\(/', $line)) {
                $failures[] = "File: {$file->getRelativePathname()}:".($index + 1).' contains a bare Exception throw.';
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Bare Exception Throw Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});
