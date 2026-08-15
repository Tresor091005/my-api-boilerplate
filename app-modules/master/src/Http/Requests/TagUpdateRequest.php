<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Master\Models\Tag;

class TagUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::normalize($this->input('name'))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tag = $this->route('tag');

        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:50',
                Rule::unique('master_tags', 'name')
                    ->where('organization_id', currentOrganizationId())
                    ->where('type', $tag instanceof Tag ? $tag->type : '')
                    ->whereNull('deleted_at')
                    ->ignore($tag instanceof Tag ? $tag->getKey() : null),
            ],
        ];
    }
}
