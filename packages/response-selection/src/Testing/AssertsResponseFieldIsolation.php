<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Testing;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResponseDeclarationAnalyzer;
use Lahatre\ResponseSelection\SelectionParser;
use Throwable;

/**
 * Response tests: every field renders alone exactly as it renders in the full selection.
 *
 * A field may read a column, a relation or a built value that only a sibling declares: the full
 * selection hides the gap because the sibling loads it, selecting the field alone exposes it. A
 * missing column or an undeclared relation throws during rendering (the request answers 500), and
 * what degrades silently renders another value, so values are compared.
 *
 * @mixin TestCase
 */
trait AssertsResponseFieldIsolation
{
    /**
     * GETs the full selection (every include path up to $depth, every field), then each field alone:
     * `?fields=<key>` at the root, `?fields=id&include=<prefix>(id),<path>(<key>)` below it.
     * Prefixes render only `id`, so nothing their fields load can satisfy the dependency. Every request
     * must answer 200 and the isolated field must render the same value on the same rows, matched by
     * the id of every row along its include path. Every node must render a row in the full
     * selection, otherwise its fields prove nothing: seed data for it.
     *
     * @param  string  $uri  GET route rendering $resourceClass, without `fields` or `include`
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function assertFieldsRenderInIsolation(string $uri, string $resourceClass, int $depth = 2): void
    {
        if ($depth < 0 || $depth > SelectionParser::MAX_DEPTH) {
            throw new InvalidArgumentException('Field isolation depth must be between 0 and '.SelectionParser::MAX_DEPTH.'.');
        }

        if (preg_match('/[?&](fields|include)=/', $uri) === 1) {
            throw new InvalidArgumentException('Pass the route without `fields` or `include`: the field isolation helper selects them itself.');
        }

        (new ResponseDeclarationAnalyzer)->validateResource($resourceClass);

        $tree = $this->isolationIncludeTree([$resourceClass], $depth);
        $full = [];

        foreach ($this->isolationBatches(array_keys($tree)) ?: [[]] as $batch) {
            [, $body] = $this->isolationGet($uri, $batch === [] ? [] : ['include' => implode(',', $batch)], 'Full selection');

            $full[''] ??= $body;

            foreach ($batch as $includePath) {
                $full[$includePath] ??= $body;
            }
        }

        foreach (['' => [$resourceClass], ...$tree] as $includePath => $classes) {
            $node = $includePath === '' ? 'the root' : "include [{$includePath}]";
            $expected = $this->isolationRows($full[$includePath], $includePath);

            $this->assertNotEmpty($expected, "The full selection renders no row at {$node}: seed data for it so its fields are checked.");

            foreach ($this->isolationFieldKeys($classes) as $key) {
                $selection = $includePath === ''
                    ? ['fields' => $key]
                    : ['fields' => 'id', 'include' => $this->isolatedInclude($includePath, $key)];

                [$url, $body] = $this->isolationGet($uri, $selection, "Field [{$key}] of {$node}");

                $this->assertIsolatedField($expected, $this->isolationRows($body, $includePath), $key, "Field [{$key}] of {$node} (GET {$url})");
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $expected  rows of the full selection
     * @param  array<string, array<string, mixed>>  $actual  rows of the isolated request
     */
    private function assertIsolatedField(array $expected, array $actual, string $key, string $subject): void
    {
        $expectedRows = array_keys($expected);
        $actualRows = array_keys($actual);
        sort($expectedRows);
        sort($actualRows);

        $this->assertSame($expectedRows, $actualRows, "{$subject}: the isolated request renders other rows than the full selection.");

        foreach ($expected as $row => $item) {
            $rendered = array_key_exists($key, $item);

            $this->assertSame($rendered, array_key_exists($key, $actual[$row]), sprintf(
                '%s: at %s, the full selection %s the key but the isolated request %s it.',
                $subject, $row, $rendered ? 'renders' : 'omits', $rendered ? 'omits' : 'renders',
            ));

            if ($rendered) {
                $this->assertSame($item[$key], $actual[$row][$key], "{$subject}: at {$row}, the isolated value differs from the full selection.");
            }
        }
    }

    /**
     * Every include path up to $depth, parents first, with the Resources rendering its rows (several
     * when a resolver picks the Resource per model).
     *
     * @param  array<int, class-string<ApiResource>>  $classes
     * @return array<string, array<int, class-string<ApiResource>>>
     */
    private function isolationIncludeTree(array $classes, int $depth, string $prefix = ''): array
    {
        if ($depth === 0) {
            return [];
        }

        $includes = [];

        foreach ($classes as $class) {
            $definition = $class::definition();

            foreach ($definition->includeNames() as $name) {
                $includes[$name] = array_values(array_unique([...$includes[$name] ?? [], ...$definition->includeResourceClasses($name)]));
            }
        }

        $tree = [];

        foreach ($includes as $name => $children) {
            $path = $prefix === '' ? $name : "{$prefix}.{$name}";
            $tree[$path] = $children;
            $tree += $this->isolationIncludeTree($children, $depth - 1, $path);
        }

        return $tree;
    }

    /**
     * Groups include paths (parents first) into selections of at most MAX_PATHS paths, each listed
     * with its prefixes and without a field list, so every node renders all its fields.
     *
     * @param  array<int, string>  $paths
     * @return array<int, array<int, string>>
     */
    private function isolationBatches(array $paths): array
    {
        $batches = [];
        $batch = [];

        foreach ($paths as $path) {
            $missing = array_values(array_diff($this->isolationPrefixes($path), $batch));

            if ($batch !== [] && count($batch) + count($missing) > SelectionParser::MAX_PATHS) {
                $batches[] = $batch;
                $batch = [];
                $missing = $this->isolationPrefixes($path);
            }

            array_push($batch, ...$missing);
        }

        return $batch === [] ? $batches : [...$batches, $batch];
    }

    /**
     * `a.b.c` → `a(id),a.b(id),a.b.c(<key>)`
     */
    private function isolatedInclude(string $includePath, string $key): string
    {
        $prefixes = $this->isolationPrefixes($includePath);
        array_pop($prefixes);

        return implode(',', [...array_map(fn (string $prefix): string => "{$prefix}(id)", $prefixes), "{$includePath}({$key})"]);
    }

    /**
     * `a.b.c` → `[a, a.b, a.b.c]`
     *
     * @return array<int, string>
     */
    private function isolationPrefixes(string $includePath): array
    {
        $segments = explode('.', $includePath);

        return array_map(fn (int $length): string => implode('.', array_slice($segments, 0, $length)), range(1, count($segments)));
    }

    /**
     * Rows rendered at $includePath, keyed by the id of every row along the path, e.g.
     * `[7].source_depot[2].open_transfers[9]`. An include renders under its own name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function isolationRows(mixed $body, string $includePath): array
    {
        $data = is_array($body) ? ($body['data'] ?? null) : null;
        $rows = $this->keyIsolationRows(is_array($data) ? (array_is_list($data) ? $data : [$data]) : [], '');

        foreach ($includePath === '' ? [] : explode('.', $includePath) as $segment) {
            $children = [];

            foreach ($rows as $row => $item) {
                $value = $item[$segment] ?? null;

                if (is_array($value)) {
                    $children += $this->keyIsolationRows(array_is_list($value) ? $value : [$value], "{$row}.{$segment}");
                }
            }

            $rows = $children;
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<string, array<string, mixed>>
     */
    private function keyIsolationRows(array $items, string $parent): array
    {
        $rows = [];

        foreach (array_values($items) as $position => $item) {
            if (is_array($item)) {
                $id = isset($item['id']) && is_scalar($item['id']) ? (string) $item['id'] : "#{$position}";
                $rows["{$parent}[{$id}]"] = $item;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, class-string<ApiResource>>  $classes
     * @return array<int, string>
     */
    private function isolationFieldKeys(array $classes): array
    {
        $keys = [];

        foreach ($classes as $class) {
            array_push($keys, ...$class::definition()->fieldKeys());
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, string>  $selection
     * @return array{0: string, 1: mixed} URL requested and decoded payload
     */
    private function isolationGet(string $uri, array $selection, string $subject): array
    {
        $query = http_build_query($selection);
        $url = $query === '' ? $uri : $uri.(str_contains($uri, '?') ? '&' : '?').$query;
        $response = $this->getJson($url);

        if ($response->getStatusCode() !== 200) {
            $this->fail(sprintf("%s: GET %s answered %d instead of 200.\n%s", $subject, $url, $response->getStatusCode(), $this->isolationFailure($response)));
        }

        return [$url, $response->json()];
    }

    private function isolationFailure(TestResponse $response): string
    {
        $exception = $response->exception;

        return $exception instanceof Throwable
            ? $exception::class.': '.$exception->getMessage()
            : mb_substr((string) $response->getContent(), 0, 2000);
    }
}
