<?php

declare(strict_types=1);

namespace Lahatre\Master\Validation;

use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use Lahatre\Master\Enums\ContactType;

final class ContactPayloadRules
{
    /** @return array<string, array<int, mixed>> */
    public static function rules(string $basePath = 'contacts'): array
    {
        return [
            $basePath                  => ['sometimes', 'array', 'max:50'],
            "{$basePath}.*"            => ['required', 'array'],
            "{$basePath}.*.id"         => ['sometimes', 'uuid'],
            "{$basePath}.*.type"       => ['required', new Enum(ContactType::class)],
            "{$basePath}.*.value"      => ['required', 'string', 'max:2048'],
            "{$basePath}.*.is_primary" => ['sometimes', 'boolean'],
        ];
    }

    public static function validate(Validator $validator, array $input, string $basePath = 'contacts'): void
    {
        $contacts = $input[$basePath] ?? null;
        if (!is_array($contacts)) {
            return;
        }

        $primaryIndexes = array_keys(array_filter(
            $contacts,
            fn (mixed $contact): bool => is_array($contact) && ($contact['is_primary'] ?? false) === true,
        ));

        if (count($primaryIndexes) > 1) {
            $validator->errors()->add($basePath, __('master::validation.multiple_primary_contacts'));
        }
    }
}
