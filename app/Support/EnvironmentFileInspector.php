<?php

declare(strict_types=1);

namespace App\Support;

final class EnvironmentFileInspector
{
    /**
     * @return array<string, string>
     */
    public function assignments(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $assignments = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $assignment = $this->parseAssignment($line);

            if ($assignment !== null) {
                $assignments[$assignment['key']] = $assignment['value'];
            }
        }

        return $assignments;
    }

    /**
     * @return array{missing: list<string>, extra: list<string>, empty: list<string>}
     */
    public function compare(string $examplePath, ?string $environmentPath = null): array
    {
        $example = $this->assignments($examplePath);
        $actual = $environmentPath !== null
            ? $this->assignments($environmentPath)
            : $this->runtimeAssignments(array_keys($example));

        return [
            'missing' => array_values(array_diff(array_keys($example), array_keys($actual))),
            'extra'   => array_values(array_diff(array_keys($actual), array_keys($example))),
            'empty'   => array_values(array_intersect(
                array_keys(array_filter($actual, static fn (string $value): bool => trim($value) === '')),
                array_keys($example),
            )),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function runtimeAssignments(array $keys): array
    {
        $assignments = [];

        foreach ($keys as $key) {
            $value = getenv($key);

            if ($value !== false) {
                $assignments[$key] = $value;
            }
        }

        return $assignments;
    }

    /**
     * @return array{key: string, value: string}|null
     */
    private function parseAssignment(string $line): ?array
    {
        if (!preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*(?:=|$)(.*)$/', $line, $matches)) {
            return null;
        }

        return [
            'key'   => $matches[1],
            'value' => trim($matches[2]),
        ];
    }
}
