<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('does not expose organization ids from ordinary resources', function (): void {
    $violations = [];
    $resourceFiles = File::allFiles(base_path('app-modules'));

    if (is_dir(base_path('app/Http/Resources'))) {
        $resourceFiles = array_merge($resourceFiles, File::allFiles(base_path('app/Http/Resources')));
    }

    foreach ($resourceFiles as $file) {
        if ($file->getExtension() !== 'php' || !str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        if ($file->getFilename() === 'UserResource.php') {
            continue;
        }

        if (str_contains($file->getContents(), 'organization_id')) {
            $violations[] = $file->getRelativePathname();
        }
    }

    expect($violations)->toBe([], "Only UserResource may expose organization_id:\n".implode("\n", $violations));
});
