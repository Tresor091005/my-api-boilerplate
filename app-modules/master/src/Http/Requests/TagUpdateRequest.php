<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Master\Models\Tag;

class TagUpdateRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tag = $this->route('tag');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('master_tags', 'name')
                    ->where('organization_id', currentOrganizationId())
                    ->where('type', $tag instanceof Tag ? $tag->type : '')
                    ->whereNull('deleted_at')
                    ->ignore($tag instanceof Tag ? $tag->getKey() : null),
            ],
        ];
    }
}
