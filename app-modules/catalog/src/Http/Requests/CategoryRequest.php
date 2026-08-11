<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Catalog\Models\Category;

class CategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::sanitize($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('category') instanceof Category;

        return [
            'name' => [
                ...($isUpdate ? [] : ['required']),
                'string',
                'max:100',
            ],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('catalog_categories', 'id')
                    ->where('organization_id', getPermissionsTeamId())
                    ->whereNull('deleted_at'),
            ],
            'is_active' => [
                ...($isUpdate ? [] : ['required']),
                'boolean',
            ],
        ];
    }
}
