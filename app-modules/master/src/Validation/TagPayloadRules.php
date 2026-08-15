<?php

declare(strict_types=1);

namespace Lahatre\Master\Validation;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class TagPayloadRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(string $basePath = 'tags', bool $required = false, bool $allowEmpty = false): array
    {
        $presence = $required ? ['required'] : [];
        $minimum = $allowEmpty ? [] : ['min:1'];

        return [
            $basePath         => [...$presence, 'array', ...$minimum, 'max:100'],
            "{$basePath}.*"   => ['required', 'array', ...$minimum, 'max:100'],
            "{$basePath}.*.*" => ['required', 'string', 'max:50'],
        ];
    }

    public static function validate(Validator $validator, array $input, string $basePath): void
    {
        foreach (self::resolve($input, explode('.', $basePath)) as [$path, $tags]) {
            if (!is_array($tags)) {
                continue;
            }

            foreach ($tags as $type => $names) {
                if (!is_string($type) || preg_match('/^[A-Za-z][A-Za-z0-9_-]{1,49}$/', $type) !== 1) {
                    $validator->errors()->add($path, __('master::validation.tag_type_invalid'));
                }

                if (!is_array($names)) {
                    continue;
                }

                foreach ($names as $index => $name) {
                    if (is_string($name) && Str::normalize($name) === '') {
                        $validator->errors()->add(
                            "{$path}.{$type}.{$index}",
                            __('master::validation.tag_name_invalid'),
                        );
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<int, string>  $segments
     * @return iterable<array{string, mixed}>
     */
    private static function resolve(array $value, array $segments, string $path = ''): iterable
    {
        if ($segments === []) {
            yield [$path, $value];

            return;
        }

        $segment = array_shift($segments);
        if ($segment === '*') {
            foreach ($value as $key => $nestedValue) {
                if (is_array($nestedValue)) {
                    yield from self::resolve($nestedValue, $segments, self::appendPath($path, (string) $key));
                }
            }

            return;
        }

        if (array_key_exists($segment, $value)) {
            yield from self::resolve(
                is_array($value[$segment]) ? $value[$segment] : [],
                $segments,
                self::appendPath($path, $segment),
            );
        }
    }

    private static function appendPath(string $path, string $segment): string
    {
        return $path === '' ? $segment : "{$path}.{$segment}";
    }
}
