<?php

declare(strict_types=1);

namespace Lahatre\Master\Validation;

use Illuminate\Validation\Validator;

final class AddressPayloadRules
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(string $basePath = 'addresses'): array
    {
        return [
            $basePath                  => ['sometimes', 'array', 'max:50'],
            "{$basePath}.*"            => ['required', 'array'],
            "{$basePath}.*.id"         => ['sometimes', 'uuid'],
            "{$basePath}.*.line"       => ['required', 'string', 'max:500'],
            "{$basePath}.*.city"       => ['required', 'string', 'max:100'],
            "{$basePath}.*.country"    => ['required', 'string', 'max:100'],
            "{$basePath}.*.is_primary" => ['sometimes', 'boolean'],
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
