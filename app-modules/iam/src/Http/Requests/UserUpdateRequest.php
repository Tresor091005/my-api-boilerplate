<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'first_name'             => ['filled', 'string', 'max:100'],
            'last_name'              => ['filled', 'string', 'max:100'],
            'default_member_role_id' => ['nullable', 'string', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'last_name'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::sanitize($this->input($field))]);
            }
        }
    }
}
