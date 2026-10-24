<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Testing;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Declaration\ResponseDeclarationAnalyzer;
use Lahatre\ResponseSelection\Selection\SelectionParser;
use Throwable;

/**
 * Test helper that checks every field shows the same value when requested alone as in the full
 * response. It catches a field that only works because another field loads its data.
 *
 * @mixin TestCase
 */
trait AssertsResponseFieldIsolation
{
    /**
     * Requests the full response, then each field alone, and checks each field shows the same value.
     * Seed at least one row for the Resource and for each of its includes up to $depth.
     *
     * @param  string  $uri  GET route returning $resourceClass, without `fields` or `include`
     * @param  class-string<ApiResource>  $resourceClass
     */
    protected function assertFieldsRenderInIsolation(string $uri, string $resourceClass, int $depth = 2): void
    {
        if ($depth < 0 || $depth > SelectionParser::maxDepth()) {
            throw new InvalidArgumentException('Field isolation depth must be between 0 and '.SelectionParser::maxDepth().'.');
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
     * Every include path up to $depth, with the Resources it can show.
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
     * Splits the include paths into requests that stay under the configured path limit.
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

            if ($batch !== [] && count($batch) + count($missing) > SelectionParser::maxPaths()) {
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
     * Rows shown at $includePath, keyed by the ids along the path, such as `[7].author[2]`.
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
