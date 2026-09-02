<?php

declare(strict_types=1);

namespace Lahatre\Master\Validation;

use Illuminate\Validation\Validator;

final class AddressPayloadRules
{
    /** @return array<string, array<int, mixed>> */
    public static function singleRules(string $basePath = 'address', bool $required = false): array
    {
        return [
            $basePath             => $required ? ['required', 'array'] : ['nullable', 'array'],
            "{$basePath}.line"    => ["required_with:{$basePath}", 'string', 'max:500'],
            "{$basePath}.city"    => ["required_with:{$basePath}", 'string', 'max:100'],
            "{$basePath}.country" => ["required_with:{$basePath}", 'string', 'max:100'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(string $basePath = 'addresses'): array
    {
        return [
            $basePath                  => ['array', 'max:50'],
            "{$basePath}.*"            => ['required', 'array'],
            "{$basePath}.*.id"         => ['uuid'],
            "{$basePath}.*.line"       => ['required', 'string', 'max:500'],
            "{$basePath}.*.city"       => ['required', 'string', 'max:100'],
            "{$basePath}.*.country"    => ['required', 'string', 'max:100'],
            "{$basePath}.*.is_primary" => ['boolean'],
        ];
    }

    public static function validate(Validator $validator, array $input, string $basePath = 'addresses'): void
    {
        $addresses = $input[$basePath] ?? null;
        if (!is_array($addresses)) {
            return;
        }

        $primaryIndexes = array_keys(array_filter(
            $addresses,
            fn (mixed $address): bool => is_array($address) && ($address['is_primary'] ?? false) === true,
        ));

        if (count($primaryIndexes) > 1) {
            $validator->errors()->add($basePath, __('master::validation.multiple_primary_addresses'));
        }
    }
}
