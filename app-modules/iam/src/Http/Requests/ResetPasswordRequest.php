<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'token'    => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'confirmed'],
        ];
    }
}
