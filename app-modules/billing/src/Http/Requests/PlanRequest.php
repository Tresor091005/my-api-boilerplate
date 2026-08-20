<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Lahatre\Billing\Models\Plan;

class PlanRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::sanitize($this->input('name'))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $isUpdate = $this->route('plan') instanceof Plan;

        return [
            'name' => [
                ...($isUpdate ? [] : ['required']),
                'string',
                'min:1',
                'max:100',
            ],
            'is_active' => [
                ...($isUpdate ? [] : ['required']),
                'boolean',
            ],
        ];
    }
}
