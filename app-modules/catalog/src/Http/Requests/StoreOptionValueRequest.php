<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StoreOptionValueRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!is_array($this->input('values'))) {
            return;
        }

        $values = Arr::where($this->input('values'), fn (mixed $value): bool => is_string($value));
        $this->merge([
            'values' => array_values(array_unique(array_map(
                fn (string $value): string => Str::normalize($value),
                $values,
            ))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'values'   => ['required', 'array', 'min:1', 'max:100'],
            'values.*' => ['string', 'max:100'],
        ];
    }
}
