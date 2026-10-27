<?php

declare(strict_types=1);

use Lahatre\Shared\Exceptions\AssertionException;
use Symfony\Component\Finder\Finder;

it('ensures all concrete module exceptions extend AssertionException', function (): void {
    $finder = new Finder;
    $finder
        ->files()
        ->in(base_path('app-modules'))
        ->path('#/src/Exceptions/#')
        ->name('*.php');

    $failures = [];

    foreach ($finder as $file) {
        if ($file->getRealPath() === base_path('app-modules/shared/src/Exceptions/AssertionException.php')) {
            continue;
        }

        $className = resolveFullyQualifiedClassName($file);

        if ($className === null || !class_exists($className)) {
            $failures[] = "Could not resolve exception class from '{$file->getRelativePathname()}'.";
            continue;
        }

        $reflection = new ReflectionClass($className);

        if ($reflection->isAbstract()) {
            continue;
        }

        if (!$reflection->isSubclassOf(AssertionException::class)) {
            $failures[] = "Exception '{$className}' in '{$file->getRelativePathname()}' must extend '".AssertionException::class."'.";
        }
    }

    if ($failures !== []) {
        $this->fail("Assertion Exception Contract Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});
