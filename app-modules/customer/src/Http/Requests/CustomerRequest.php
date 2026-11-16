<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use Lahatre\Customer\Enums\CustomerType;
use Lahatre\Organization\Contracts\OrganizationInterface;
use Lahatre\Shared\Rules\YmdDate;

class CustomerRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        // on update an omitted field is kept, so only create requires them
        $presence = $this->route('customer') !== null ? [] : ['required'];

        return [
            'type'                  => [...$presence, new Enum(CustomerType::class)],
            'name'                  => [...$presence, 'string', 'max:100'],
            'identification_number' => ['nullable', 'string', 'max:100'],
            'is_active'             => [...$presence, 'boolean'],
            'payment_due_date'      => [
                'bail',
                'nullable',
                new YmdDate,
                'after_or_equal:'.app(OrganizationInterface::class)->today(currentOrganizationId())->toDateString(),
            ],
            'price_level_id' => [
                'nullable',
                'uuid',
                Rule::exists('catalog_price_levels', 'id')
                    ->where('organization_id', currentOrganizationId())
                    ->whereNull('deleted_at'),
            ],
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
