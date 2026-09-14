<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $name = basename(str_replace('\\', '/', $this->input('name')));
            $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? $name;

            $this->merge(['name' => Str::sanitize($name)]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name'      => ['string', 'min:1', 'max:255'],
            'folder_id' => [
                'nullable',
                'uuid',
                Rule::exists('library_folders', 'id')
                    ->where('organization_id', currentOrganizationId())
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
