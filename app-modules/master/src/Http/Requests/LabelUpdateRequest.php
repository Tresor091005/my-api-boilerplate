<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Master\Models\Label;

class LabelUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('value'))) {
            $this->merge(['value' => Str::normalize($this->input('value'))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $label = $this->route('label');

        return [
            'value' => [
                'required',
                'string',
                'min:1',
                'max:50',
                Rule::unique('master_labels', 'value')
                    ->where('organization_id', currentOrganizationId())
                    ->where('group', $label instanceof Label ? $label->group : '')
                    ->whereNull('deleted_at')
                    ->ignore($label instanceof Label ? $label->getKey() : null),
            ],
        ];
    }
}
