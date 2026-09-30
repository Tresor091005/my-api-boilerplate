<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('reserves the account resource for responses to the account owner', function (): void {
    $allowedPaths = [
        base_path('app-modules/iam/src/Http/Resources/UserResource.php'),
        base_path('app-modules/iam/src/Http/Resources/AuthResource.php'),
        base_path('app-modules/iam/src/Http/Controllers/AuthController.php'),
    ];
    $violations = [];

    $files = array_merge(File::allFiles(base_path('app')), File::allFiles(base_path('app-modules')));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || (!str_starts_with($file->getPathname(), base_path('app').DIRECTORY_SEPARATOR)
            && !str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR))) {
            continue;
        }
        if (preg_match('/\bUserResource\b/', $file->getContents()) && !in_array($file->getPathname(), $allowedPaths, true)) {
            $violations[] = $file->getRelativePathname();
        }
    }

    expect($violations)->toBe([], 'Other users must be serialized through UserProfileResource.');
});
