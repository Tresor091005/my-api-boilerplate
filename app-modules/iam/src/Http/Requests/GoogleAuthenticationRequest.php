<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class GoogleAuthenticationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'last_name'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::sanitize($this->input($field))]);
            }
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'uuid'],
            'credential'   => ['required', 'string', 'max:16384'],
            'first_name'   => ['filled', 'string', 'max:100'],
            'last_name'    => ['filled', 'string', 'max:100'],
        ];
    }
}
