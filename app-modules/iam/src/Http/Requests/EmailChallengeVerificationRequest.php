<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class EmailChallengeVerificationRequest extends FormRequest
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
            'code'         => ['required', 'string', 'regex:/\A[0-9]{6}\z/'],
            'first_name'   => ['string', 'min:1', 'max:100'],
            'last_name'    => ['string', 'min:1', 'max:100'],
        ];
    }
}
