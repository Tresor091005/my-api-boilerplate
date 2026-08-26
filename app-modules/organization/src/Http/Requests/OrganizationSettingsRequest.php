<?php

declare(strict_types=1);

namespace Lahatre\Organization\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class OrganizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enable_currencies' => [
                $this->isMethod('PATCH') ? 'required' : 'sometimes',
                'array',
                'min:1',
            ],
            'enable_currencies.*' => [
                'string',
                'size:3',
                'alpha',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enable_currencies' => collect($this->input('enable_currencies', []))
                ->map(fn (mixed $code): mixed => is_string($code) ? Str::upper($code) : $code)
                ->all(),
        ]);
    }
}
