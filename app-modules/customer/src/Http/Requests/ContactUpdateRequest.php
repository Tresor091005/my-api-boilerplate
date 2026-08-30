<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Lahatre\Master\Enums\ContactType;

final class ContactUpdateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type'       => [new Enum(ContactType::class)],
            'value'      => ['string', 'max:2048'],
            'is_primary' => ['boolean'],
        ];
    }
}
