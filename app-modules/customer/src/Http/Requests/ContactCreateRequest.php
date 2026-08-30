<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Lahatre\Master\Validation\ContactPayloadRules;

final class ContactCreateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = ContactPayloadRules::rules();
        $rules['contacts'] = ['required', 'array', 'min:1', 'max:50'];
        $rules['contacts.*.id'] = ['prohibited'];

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            ContactPayloadRules::validate($validator, $this->all());
        }];
    }
}
