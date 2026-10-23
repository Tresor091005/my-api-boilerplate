<?php

declare(strict_types=1);

namespace Lahatre\Shared\Http\Response;

use Illuminate\Validation\ValidationException;

/**
 * Parses the `fields` and `include` query parameters into a normalized SelectionTree.
 *
 * Grammar (include):
 *   include   := entry ("," entry)*
 *   entry     := path [ "(" fieldList ")" ]
 *   path      := segment ("." segment)*        ; <= MAX_DEPTH segments
 *   fieldList := key ("," key)*
 *   segment, key := [A-Za-z_][A-Za-z0-9_]*
 *
 * Every prefix of a path is implicitly included with all its fields unless the prefix is
 * given with its own list. Whitespace is trimmed, empty entries are ignored, duplicate paths
 * are merged and their field lists unioned (an explicit path without list selects all).
 * Names are not resolved here: SelectionValidator checks them against the definitions.
 */
final class SelectionParser
{
    public const MAX_DEPTH = 6;

    public const MAX_PATHS = 40;

    public const MAX_FIELDS_PER_NODE = 100;

    private const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*\z/';

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
                $this->fail('fields', __('shared::response.fields_not_allowed_in_fields'));
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
                if (count($segments) > self::MAX_DEPTH) {
                    $this->fail('include', __('shared::response.depth_exceeded', [
                        'path' => $path,
                        'max'  => self::MAX_DEPTH,
                    ]));
                }

                // implicit prefixes
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
        if ($pathCount > self::MAX_PATHS) {
            $this->fail('include', __('shared::response.too_many_paths', [
                'max'   => self::MAX_PATHS,
                'count' => $pathCount,
            ]));
        }

        foreach ($nodes as $path => $node) {
            $count = count($node['fields'] ?? []);
            if ($count > self::MAX_FIELDS_PER_NODE) {
                $this->fail($path === '' ? 'fields' : 'include', __('shared::response.too_many_fields', [
                    'max'   => self::MAX_FIELDS_PER_NODE,
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
            $this->fail($parameter, __('shared::response.invalid_type', ['parameter' => $parameter]));
        }

        return trim($value) === '' ? null : $value;
    }

    private function assertCharacters(string $parameter, string $value): void
    {
        if (preg_match('/[^A-Za-z0-9_.,()\s]/', $value)) {
            $this->fail($parameter, __('shared::response.invalid_characters', ['parameter' => $parameter]));
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
                    $this->fail('include', __('shared::response.nested_parentheses', ['parameter' => 'include']));
                }
                $depth++;
            } elseif ($char === ')') {
                if ($depth === 0) {
                    $this->fail('include', __('shared::response.unbalanced_parentheses', ['parameter' => 'include']));
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
            $this->fail('include', __('shared::response.unbalanced_parentheses', ['parameter' => 'include']));
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
                $this->fail('include', __('shared::response.invalid_path', ['path' => $entry]));
            }

            $path = trim(substr($entry, 0, $open));
            $list = $this->parseFieldList('include', substr($entry, $open + 1, -1));
        }

        $segments = array_map('trim', explode('.', $path));
        foreach ($segments as $segment) {
            if (!preg_match(self::IDENTIFIER, $segment)) {
                $this->fail('include', __('shared::response.invalid_path', ['path' => $path]));
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
                $this->fail($parameter, __('shared::response.invalid_field', ['field' => $key]));
            }
        }

        return array_values(array_unique($keys));
    }

    private function fail(string $parameter, string $message): never
    {
        throw ValidationException::withMessages([$parameter => [$message]]);
    }
}
