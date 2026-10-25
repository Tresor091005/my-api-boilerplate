<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Lahatre\ResponseSelection\ApiResource;

it('declares the fields of every ApiResource first, then its includes, then its virtual includes', function (): void {
    $violations = [];

    foreach (File::allFiles(base_path('app-modules')) as $file) {
        $source = $file->getContents();

        if ($file->getExtension() !== 'php' || !str_contains($file->getPathname(), '/src/Http/Resources/')
            || preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1
            || preg_match('/^(?:final |abstract )?class\s+(\w+)/m', $source, $class) !== 1) {
            continue;
        }

        $resourceClass = $namespace[1].'\\'.$class[1];

        // an abstract Resource is checked through the Resources extending it
        if (!is_subclass_of($resourceClass, ApiResource::class) || (new ReflectionClass($resourceClass))->isAbstract()) {
            continue;
        }

        $definition = $resourceClass::definition();
        $previousRank = 0;

        foreach ($definition->members() as $member) {
            $rank = $definition->hasField($member) ? 0 : ($definition->isVirtualInclude($member) ? 2 : 1);

            if ($rank < $previousRank) {
                $violations[] = "{$resourceClass} declares [{$member}] too late";
            }

            $previousRank = max($previousRank, $rank);
        }
    }

    expect($violations)->toBe([], 'Declare fields, then includes, then virtual includes.');
});
