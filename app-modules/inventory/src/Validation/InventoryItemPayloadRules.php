<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Validation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class InventoryItemPayloadRules
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public static function rules(string $basePath = 'inventory', bool $required = false): array
    {
        return [
            $basePath                            => [$required ? 'required' : 'sometimes', 'array'],
            "{$basePath}.stock_tracking_enabled" => ['sometimes', 'boolean'],
            "{$basePath}.is_expirable"           => ['sometimes', 'boolean'],
            "{$basePath}.deduction_strategy"     => ['sometimes', 'nullable', Rule::in(['fifo', 'fefo', 'manual'])],
        ];
    }

    /**
     * Validate relationships between fields while preserving nested error paths.
     *
     * @param  array<string, mixed>  $input
     */
    public static function validate(Validator $validator, array $input, string $basePath): void
    {
        foreach (self::resolve($input, explode('.', $basePath)) as [$path, $payload]) {
            if (!is_array($payload)) {
                continue;
            }

            $strategy = $payload['deduction_strategy'] ?? null;
            $isExpirable = $payload['is_expirable'] ?? null;

            if ($strategy === 'fifo' && $isExpirable === true) {
                $validator->errors()->add(
                    "{$path}.deduction_strategy",
                    __('inventory::validation.fifo_expirable_prohibited'),
                );
            }

            if ($strategy === 'fefo' && $isExpirable === false) {
                $validator->errors()->add(
                    "{$path}.deduction_strategy",
                    __('inventory::validation.fefo_non_expirable_prohibited'),
                );
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
