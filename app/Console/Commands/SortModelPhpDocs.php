<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class SortModelPhpDocs extends Command
{
    protected $signature = 'ide-helper:models:sort';

    protected $description = 'Sort generated model PHPDoc annotations';

    public function handle(): int
    {
        $changedFiles = 0;

        foreach ($this->modelFiles() as $file) {
            $contents = File::get($file);
            $sortedContents = preg_replace_callback(
                '/\\/\\*\\*(.*?)\\*\\/(?=\\s*(?:#\\[[^\\]]+\\]\\s*)?(?:(?:final|abstract)\\s+)?class\\b)/s',
                fn (array $match): string => $this->sortDocblock($match[0]),
                $contents,
            );

            if ($sortedContents === null || $sortedContents === $contents) {
                continue;
            }

            File::put($file, $sortedContents);
            $changedFiles++;
        }

        $this->info("Sorted model PHPDoc blocks in {$changedFiles} file(s).");

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function modelFiles(): array
    {
        $files = [];

        foreach (config('ide-helper.model_locations', []) as $location) {
            $path = base_path($location);
            $directories = str_contains($path, '*')
                ? glob($path, GLOB_ONLYDIR) ?: []
                : [$path];

            foreach ($directories as $directory) {
                if (!File::isDirectory($directory)) {
                    continue;
                }

                foreach (File::allFiles($directory) as $file) {
                    if ($file->getExtension() === 'php') {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }

        return array_values(array_unique($files));
    }

    private function sortDocblock(string $docblock): string
    {
        preg_match_all(
            '/^\\s*\\*\\s+(?:\\*\\s+)?@(property|property-read|property-write|method|mixin)\\b.*$/m',
            $docblock,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        if ($matches[0] === []) {
            return $docblock;
        }

        $annotations = array_values(array_unique(array_map(
            static fn (array $match): string => (string) preg_replace(
                '/^\\s*\\*\\s+(?:\\*\\s+)?/',
                '',
                $match[0],
            ),
            $matches[0],
        )));
        $seenSemanticAnnotations = [];
        $annotations = array_values(array_filter(
            $annotations,
            function (string $annotation) use (&$seenSemanticAnnotations): bool {
                $semanticKey = match (true) {
                    preg_match('/^@(property(?:-(?:read|write))?)\\s+.+?\\s+\\$(\\w+)/', $annotation, $property) === 1 => $property[1].' '.$property[2],
                    preg_match('/^@method static .*\\s(factory)\\(/', $annotation) === 1                               => '@method static factory',
                    default                                                                                            => $annotation,
                };

                if (isset($seenSemanticAnnotations[$semanticKey])) {
                    return false;
                }

                $seenSemanticAnnotations[$semanticKey] = true;

                return true;
            },
        ));
        $groups = [
            'property'       => [],
            'property-read'  => [],
            'property-write' => [],
            'method'         => [],
            'mixin'          => [],
        ];

        foreach ($annotations as $annotation) {
            preg_match('/@(property(?:-read|-write)?|method|mixin)\\b/', $annotation, $tag);
            $groups[$tag[1]][] = $annotation;
        }

        $orderedAnnotations = [];
        foreach (['property', 'property-read', 'property-write', 'method', 'mixin'] as $group) {
            if ($groups[$group] === []) {
                continue;
            }

            if ($orderedAnnotations !== []) {
                $orderedAnnotations[] = ' *';
            }

            array_push(
                $orderedAnnotations,
                ...array_map(
                    static fn (string $annotation): string => ' * '.$annotation,
                    $groups[$group],
                ),
            );
        }

        $firstOffset = $matches[0][0][1];
        $lastMatch = $matches[0][count($matches[0]) - 1][0];
        $lastOffset = $matches[0][count($matches[0]) - 1][1] + strlen($lastMatch);
        $replacement = implode("\n", $orderedAnnotations);

        return substr($docblock, 0, $firstOffset)
            .$replacement
            .substr($docblock, $lastOffset);
    }
}
