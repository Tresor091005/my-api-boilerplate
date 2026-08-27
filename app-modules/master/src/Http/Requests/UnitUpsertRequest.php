<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Master\Models\Unit;
use Lahatre\Shared\Rules\BulkExists;

class UnitUpsertRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('group_name'))) {
            $this->merge(['group_name' => Str::normalize($this->input('group_name'))]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $organizationId = currentOrganizationId();

        return [
            'group_id' => [
                'nullable',
                'uuid',
                Rule::exists('master_unit_groups', 'id')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at'),
            ],
            'group_name' => [
                'required_without:group_id',
                'string',
                'max:100',
                Rule::unique('master_unit_groups', 'name')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->ignore($this->input('group_id')),
            ],
            'units' => [
                'required_without:group_id',
                'nullable',
                'array',
                'min:1',
                'max:100',
                new BulkExists('master_units', 'id', 'id', 'uuid', true, [
                    'organization_id' => $organizationId,
                ]),
            ],
            'units.*.id'     => ['nullable', 'uuid'],
            'units.*.name'   => ['required', 'string', 'max:100'],
            'units.*.symbol' => ['nullable', 'string', 'max:10'],
            'units.*.ratio'  => ['nullable', 'integer', 'gt:0', 'max:'.Unit::MAX_CUSTOM_RATIO],
        ];
    }
}
