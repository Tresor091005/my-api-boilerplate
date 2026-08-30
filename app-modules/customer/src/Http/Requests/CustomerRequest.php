<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use Lahatre\Customer\Enums\CustomerType;

class CustomerRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $isUpdate = $this->route('customer') !== null;

        return [
            'type'                  => [$isUpdate ? 'sometimes' : 'required', new Enum(CustomerType::class)],
            'name'                  => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:100'],
            'identification_number' => ['nullable', 'string', 'max:100'],
            'is_active'             => ['boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('type') === CustomerType::Company->value && blank($this->input('identification_number'))) {
                $validator->errors()->add('identification_number', __('customer::validation.company_identification_required'));
            }
        }];
    }
}
