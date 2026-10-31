<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Lahatre\Master\Validation\AddressPayloadRules;

final class AddressCreateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = AddressPayloadRules::rules();
        $rules['addresses'] = ['required', 'array', 'min:1', 'max:'.config('child-limits.master_addresses.addressable_id')];
        $rules['addresses.*.id'] = ['prohibited'];

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            AddressPayloadRules::validate($validator, $this->all());
        }];
    }
}
