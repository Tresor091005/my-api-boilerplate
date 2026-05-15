<?php

declare(strict_types=1);

use Lahatre\Shared\Exceptions\AssertionException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Resolve a fully qualified class name from a PHP file.
 */
function resolveFullyQualifiedClassName(SplFileInfo $file): ?string
{
    $content = $file->getContents();

    if (!preg_match('/^namespace\s+([^;]+);/m', $content, $namespaceMatches)) {
        return null;
    }

    if (!preg_match('/^(?:abstract\s+|final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $content, $classMatches)) {
        return null;
    }

    return $namespaceMatches[1].'\\'.$classMatches[1];
}

it('ensures all concrete module exceptions extend AssertionException', function (): void {
    $finder = new Finder();
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
