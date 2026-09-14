<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lahatre\Library\Enums\FileKind;

class FileFilterRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'per_page'   => ['integer', 'min:1', 'max:100'],
            'cursor'     => ['nullable', 'string'],
            'sort_by'    => ['string', Rule::in(['name', 'size', 'created_at', 'updated_at'])],
            'sort_order' => ['string', Rule::in(['asc', 'desc'])],
            'folder_id'  => [
                'nullable',
                'uuid',
                Rule::exists('library_folders', 'id')
                    ->where('organization_id', currentOrganizationId())
                    ->whereNull('deleted_at'),
            ],
            'search'    => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:150'],
            'kind'      => ['nullable', Rule::enum(FileKind::class)],
        ];
    }
}
