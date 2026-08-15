<?php

declare(strict_types=1);

namespace Lahatre\Shared\Console\Commands;

use Illuminate\Console\Command;
use ReflectionFunction;

final class HelpersListCommand extends Command
{
    protected $signature = 'helpers:list';

    protected $description = 'List project-defined helpers and their PHPDoc descriptions';

    public function handle(): int
    {
        $helpers = $this->projectHelpers();
        $missingDescriptions = array_filter(
            $helpers,
            static fn (array $helper): bool => $helper['description'] === null,
        );

        if ($missingDescriptions !== []) {
            $this->components->error(__('shared::console.helpers_missing_descriptions'));
            $this->table(
                ['Helper', 'File', 'Line'],
                array_map(
                    static fn (array $helper): array => [
                        $helper['name'],
                        $helper['file'],
                        $helper['line'],
                    ],
                    $missingDescriptions,
                ),
            );

            return self::FAILURE;
        }

        $this->table(
            ['Helper', 'Description', 'File', 'Line'],
            array_map(
                static fn (array $helper): array => [
                    $helper['name'],
                    $helper['description'],
                    $helper['file'],
                    $helper['line'],
                ],
                $helpers,
            ),
        );

        return self::SUCCESS;
    }

    /**
     * @return list<array{name: string, description: ?string, file: string, line: int}>
     */
    private function projectHelpers(): array
    {
        $helpers = [];

        foreach (get_defined_functions()['user'] as $functionName) {
            $function = new ReflectionFunction($functionName);
            $file = $function->getFileName();

            if (!is_string($file) || !$this->isProjectFile($file)) {
                continue;
            }

            $helpers[] = [
                'name'        => $function->getName(),
                'description' => $this->extractDescription($function->getDocComment()),
                'file'        => $this->relativePath($file),
                'line'        => $function->getStartLine(),
            ];
        }

        usort(
            $helpers,
            static fn (array $left, array $right): int => [$left['file'], $left['line'], $left['name']]
                <=> [$right['file'], $right['line'], $right['name']],
        );

        return $helpers;
    }

    private function isProjectFile(string $file): bool
    {
        foreach ([base_path('app'), base_path('app-modules')] as $root) {
            if (str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function relativePath(string $file): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
    }

    private function extractDescription(string|false $docComment): ?string
    {
        if ($docComment === false) {
            return null;
        }

        $lines = preg_split('/\R/', $docComment);

        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\/\*\*?|\*\/$/', '', $line);
            $line = trim((string) preg_replace('/^\*\s?/', '', (string) $line));

            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }

            return preg_replace('/\s+/', ' ', $line) ?: null;
        }

        return null;
    }
}
