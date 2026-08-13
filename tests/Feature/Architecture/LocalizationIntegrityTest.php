<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

it('keeps referenced translations available in the English locale', function (): void {
    $files = array_merge(
        File::allFiles(base_path('app')),
        File::allFiles(base_path('app-modules')),
        File::allFiles(base_path('routes')),
    );
    $missing = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        $contents = $file->getContents();
        preg_match_all("~(?:__|trans|trans_choice)\\(\\s*['\"]([^'\"]+)['\"]~", $contents, $matches);

        foreach ($matches[1] as $translationKey) {
            if (!preg_match('~^([^:]+)::([^.]+)\\.(.+)$~', $translationKey, $parts)) {
                continue;
            }

            [, $module, $fileName, $key] = $parts;
            $translationFile = base_path("app-modules/{$module}/resources/lang/en/{$fileName}.php");

            if (!is_file($translationFile)) {
                $missing[] = "{$translationKey} referenced by {$file->getRelativePathname()} (missing {$translationFile})";
                continue;
            }

            $translations = require $translationFile;
            if (!is_array($translations) || !Arr::has($translations, $key)) {
                $missing[] = "{$translationKey} referenced by {$file->getRelativePathname()}";
            }
        }
    }

    expect($missing)->toBe([], implode("\n", $missing));
});

it('keeps translation files structurally consistent', function (): void {
    $invalid = [];

    foreach (File::allFiles(base_path('app-modules')) as $file) {
        if (!str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR) || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = $file->getContents();
        if (!str_starts_with($contents, "<?php\n\ndeclare(strict_types=1);")) {
            $invalid[] = "Missing strict header: {$file->getRelativePathname()}";
            continue;
        }

        if (!is_array(require $file->getPathname())) {
            $invalid[] = "Translation file must return an array: {$file->getRelativePathname()}";
        }

        $translations = require $file->getPathname();
        $values = Arr::dot($translations);
        foreach ($values as $key => $value) {
            if (is_string($value) && preg_match('/\\{[a-zA-Z_][a-zA-Z0-9_]*\\}/', $value)) {
                $invalid[] = "Non-Laravel placeholder in {$file->getRelativePathname()} at [{$key}]";
            }
        }
    }

    expect($invalid)->toBe([], implode("\n", $invalid));
});
