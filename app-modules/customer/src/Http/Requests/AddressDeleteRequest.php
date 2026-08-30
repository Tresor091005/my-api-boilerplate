<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class AddressDeleteRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
