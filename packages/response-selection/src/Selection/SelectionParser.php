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

            foreach ($this->splitEntries($includeString) as $entry) {
                [$path, $list] = $this->parseEntry($entry);

                $segments = explode('.', $path);
                if (count($segments) > self::maxDepth()) {
                    $this->fail('include', __('response-selection::validation.depth_exceeded', [
                        'path' => $path,
                        'max'  => self::maxDepth(),
                    ]));
                }

                // Including `a.b` also includes `a`
                for ($i = 1; $i < count($segments); $i++) {
                    $prefix = implode('.', array_slice($segments, 0, $i));
                    $nodes[$prefix] ??= ['all' => false, 'fields' => null];
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
        if ($pathCount > self::maxPaths()) {
            $this->fail('include', __('response-selection::validation.too_many_paths', [
                'max'   => self::maxPaths(),
                'count' => $pathCount,
            ]));
        }

        foreach ($nodes as $path => $node) {
            $count = count($node['fields'] ?? []);
            if ($count > self::maxFieldsPerNode()) {
                $this->fail($path === '' ? 'fields' : 'include', __('response-selection::validation.too_many_fields', [
                    'max'   => self::maxFieldsPerNode(),
                    'count' => $count,
                    'path'  => $path === '' ? 'root' : $path,
                ]));
            }
        }

        return new SelectionTree($this->buildNode('', $nodes));
    }

    /**
     * @param  array<string, array{all: bool, fields: array<int, string>|null}>  $nodes
     */
    private function buildNode(string $path, array $nodes): SelectionNode
    {
        $children = [];
        $depth = $path === '' ? 0 : substr_count($path, '.') + 1;

        foreach ($nodes as $candidate => $_) {
            if ($candidate === '') {
                continue;
            }

            $isChild = $path === ''
                ? !str_contains($candidate, '.')
                : str_starts_with($candidate, $path.'.') && substr_count($candidate, '.') === $depth;

            if ($isChild) {
                $segments = explode('.', $candidate);
                $children[end($segments)] = $this->buildNode($candidate, $nodes);
            }
        }

        $node = $nodes[$path];
        $fields = $node['all'] ? null : $node['fields'];

        return new SelectionNode($path, $fields, $children);
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
        $entries = [];
        $current = '';
        $depth = 0;

        foreach (str_split($include) as $char) {
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
            } elseif ($char === ',' && $depth === 0) {
                $entries[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($depth !== 0) {
            $this->fail('include', __('response-selection::validation.unbalanced_parentheses', ['parameter' => 'include']));
        }

        $entries[] = $current;

        return array_values(array_filter(array_map('trim', $entries), fn (string $entry) => $entry !== ''));
    }

    /**
     * @return array{0: string, 1: array<int, string>|null}
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

        $segments = array_map('trim', explode('.', $path));
        foreach ($segments as $segment) {
            if (!preg_match(self::IDENTIFIER, $segment)) {
                $this->fail('include', __('response-selection::validation.invalid_path', ['path' => $path]));
            }
        }

        return [implode('.', $segments), $list];
    }

    /**
     * @return array<int, string>
     */
    private function parseFieldList(string $parameter, string $list): array
    {
        $keys = array_filter(array_map('trim', explode(',', $list)), fn (string $key) => $key !== '');

        foreach ($keys as $key) {
            if (!preg_match(self::IDENTIFIER, $key)) {
                $this->fail($parameter, __('response-selection::validation.invalid_field', ['field' => $key]));
            }
        }

        return array_values(array_unique($keys));
    }

    private function fail(string $parameter, string $message): never
    {
        throw ValidationException::withMessages([$parameter => [$message]]);
    }
}
