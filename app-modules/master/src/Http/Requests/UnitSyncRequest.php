<?php

declare(strict_types=1);

namespace Lahatre\Master\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UnitSyncRequest extends FormRequest
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
        $organizationId = getPermissionsTeamId();

        return [
            'group_id' => [
                'nullable',
                'uuid',
                Rule::exists('master_unit_groups', 'id')
                    ->whereNotNull('organization_id')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at'),
            ],
            'group_name' => [
                'required_without:group_id',
                'string',
                'max:255',
                Rule::unique('master_unit_groups', 'name')
                    ->whereNotNull('organization_id')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->ignore($this->input('group_id')),
            ],
            'units'          => ['required_without:group_id', 'nullable', 'array', 'min:1'],
            'units.*.id'     => ['nullable', 'uuid'],
            'units.*.name'   => ['required', 'string'],
            'units.*.symbol' => ['nullable', 'string'],
            'units.*.ratio'  => ['nullable', 'integer', 'gt:0'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (!is_array($this->input('units'))) {
                return;
            }

            $unitIds = collect($this->input('units'))->pluck('id')->filter()->all();
            if ($unitIds === []) {
                return;
            }

            $existingCount = DB::table('master_units')
                ->whereIn('id', $unitIds)
                ->whereNotNull('organization_id')
                ->where('organization_id', getPermissionsTeamId())
                ->whereNull('deleted_at')
                ->count();

            if ($existingCount !== count($unitIds)) {
                $validator->errors()->add('units', __('shared::validation.bulk_exists', ['attribute' => 'units']));
            }
        }];
    }
}
