<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Selection;

use Illuminate\Validation\ValidationException;

/**
 * Turns the `fields` and `include` query parameters, such as `include=author(name),comments.author`,
 * into a SelectionTree. It checks only the syntax; SelectionValidator checks the names.
 */
final class SelectionParser
{
    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*\z/';

    public static function maxDepth(): int
    {
        return (int) config('response-selection.limits.max_depth');
    }

    public static function maxPaths(): int
    {
        return (int) config('response-selection.limits.max_paths');
    }

    public static function maxFieldsPerNode(): int
    {
        return (int) config('response-selection.limits.max_fields_per_node');
    }

    /**
     * @throws ValidationException
     */
    public function parse(mixed $fields, mixed $include): SelectionTree
    {
        $fieldsString = $this->assertString('fields', $fields);
        $includeString = $this->assertString('include', $include);

        /** @var array<string, array{all: bool, fields: array<int, string>|null}> $nodes */
        $nodes = ['' => ['all' => false, 'fields' => null]];

        if ($fieldsString !== null) {
            $this->assertCharacters('fields', $fieldsString);

            if (preg_match('/[().]/', $fieldsString)) {
                $this->fail('fields', __('response-selection::validation.fields_not_allowed_in_fields'));
            }

            $rootFields = $this->parseFieldList('fields', $fieldsString);
            if ($rootFields !== []) {
                $nodes['']['fields'] = $rootFields;
            }
        }

        if ($includeString !== null) {
            $this->assertCharacters('include', $includeString);
            $maxDepth = self::maxDepth();

            foreach ($this->splitEntries($includeString) as $entry) {
                [$path, $list, $segments] = $this->parseEntry($entry);

                $segmentCount = count($segments);
                if ($segmentCount > $maxDepth) {
                    $this->fail('include', __('response-selection::validation.depth_exceeded', [
                        'path' => $path,
                        'max'  => $maxDepth,
                    ]));
                }

                // Including `a.b` also includes `a`
                $prefix = $segments[0];
                for ($i = 1; $i < $segmentCount; $i++) {
                    $nodes[$prefix] ??= ['all' => false, 'fields' => null];
                    $prefix .= '.'.$segments[$i];
                }

                $nodes[$path] ??= ['all' => false, 'fields' => null];

                if ($list === null) {
                    $nodes[$path]['all'] = true;
                } else {
                    $nodes[$path]['fields'] = array_values(array_unique(array_merge($nodes[$path]['fields'] ?? [], $list)));
                }
            }
        }

        $pathCount = count($nodes) - 1;
        $maxPaths = self::maxPaths();
        if ($pathCount > $maxPaths) {
            $this->fail('include', __('response-selection::validation.too_many_paths', [
                'max'   => $maxPaths,
                'count' => $pathCount,
            ]));
        }

        $maxFields = self::maxFieldsPerNode();
        foreach ($nodes as $path => $node) {
            $count = count($node['fields'] ?? []);
            if ($count > $maxFields) {
                $this->fail($path === '' ? 'fields' : 'include', __('response-selection::validation.too_many_fields', [
                    'max'   => $maxFields,
                    'count' => $count,
                    'path'  => $path === '' ? 'root' : $path,
                ]));
            }
        }

        // a parent path is always inserted before its children, so one pass groups children by parent
        $children = [];
        foreach ($nodes as $path => $_) {
            if ($path === '') {
                continue;
            }
            $dot = strrpos($path, '.');
            if ($dot === false) {
                $children[''][$path] = $path;
            } else {
                $children[substr($path, 0, $dot)][substr($path, $dot + 1)] = $path;
            }
        }

        return new SelectionTree($this->buildNode('', $nodes, $children));
    }

    /**
     * @param  array<string, array{all: bool, fields: array<int, string>|null}>  $nodes
     * @param  array<string, array<string, string>>  $children
     */
    private function buildNode(string $path, array $nodes, array $children): SelectionNode
    {
        $built = [];

        foreach ($children[$path] ?? [] as $name => $childPath) {
            $built[$name] = $this->buildNode($childPath, $nodes, $children);
        }

        $node = $nodes[$path];

        return new SelectionNode($path, $node['all'] ? null : $node['fields'], $built);
    }

    private function assertString(string $parameter, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            $this->fail($parameter, __('response-selection::validation.invalid_type', ['parameter' => $parameter]));
        }

        return trim($value) === '' ? null : $value;
    }

    private function assertCharacters(string $parameter, string $value): void
    {
        if (preg_match('/[^A-Za-z0-9_.,()\s]/', $value)) {
            $this->fail($parameter, __('response-selection::validation.invalid_characters', ['parameter' => $parameter]));
        }
    }

    /**
     * Splits on commas outside parentheses.
     *
     * @return array<int, string>
     */
    private function splitEntries(string $include): array
    {
        $raw = [];
        $depth = 0;
        $start = 0;
        $length = strlen($include);
        $offset = strcspn($include, '(),');

        while ($offset < $length) {
            $char = $include[$offset];

            if ($char === '(') {
                if ($depth > 0) {
                    $this->fail('include', __('response-selection::validation.nested_parentheses', ['parameter' => 'include']));
                }
                $depth++;
            } elseif ($char === ')') {
                if ($depth === 0) {
                    $this->fail('include', __('response-selection::validation.unbalanced_parentheses', ['parameter' => 'include']));
                }
                $depth--;
            } elseif ($depth === 0) {
                $raw[] = substr($include, $start, $offset - $start);
                $start = $offset + 1;
            }

            $offset += 1 + strcspn($include, '(),', $offset + 1);
        }

        if ($depth !== 0) {
            $this->fail('include', __('response-selection::validation.unbalanced_parentheses', ['parameter' => 'include']));
        }

        $raw[] = substr($include, $start);
        $entries = [];

        foreach ($raw as $entry) {
            $entry = trim($entry);
            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array{0: string, 1: array<int, string>|null, 2: array<int, string>}
     */
    private function parseEntry(string $entry): array
    {
        $list = null;
        $path = $entry;

        $open = strpos($entry, '(');
        if ($open !== false) {
            if (!str_ends_with($entry, ')')) {
                $this->fail('include', __('response-selection::validation.invalid_path', ['path' => $entry]));
            }

            $path = trim(substr($entry, 0, $open));
            $list = $this->parseFieldList('include', substr($entry, $open + 1, -1));
        }

        $segments = [];
        foreach (explode('.', $path) as $segment) {
            $segment = trim($segment);
            if (!preg_match(self::IDENTIFIER, $segment)) {
                $this->fail('include', __('response-selection::validation.invalid_path', ['path' => $path]));
            }
            $segments[] = $segment;
        }

        return [implode('.', $segments), $list, $segments];
    }

    /**
     * @return array<int, string>
     */
    private function parseFieldList(string $parameter, string $list): array
    {
        $keys = [];

        foreach (explode(',', $list) as $key) {
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            if (!preg_match(self::IDENTIFIER, $key)) {
                $this->fail($parameter, __('response-selection::validation.invalid_field', ['field' => $key]));
            }

            $keys[$key] = true;
        }

        return array_keys($keys);
    }

    private function fail(string $parameter, string $message): never
    {
        throw ValidationException::withMessages([$parameter => [$message]]);
    }
}
