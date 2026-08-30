<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class AddressUpdateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'line'       => ['string', 'max:500'],
            'city'       => ['string', 'max:100'],
            'country'    => ['string', 'max:100'],
            'is_primary' => ['boolean'],
        ];
    }
}
