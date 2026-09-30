<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Iam\Models\Role;
use Lahatre\Shared\Rules\BulkExists;

class RoleUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prepared = [];

        foreach (['name', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $prepared[$field] = Str::sanitize($this->input($field));
            }
        }

        $this->merge($prepared);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'string', 'max:100',
                Rule::unique('iam_roles', 'name')
                    ->withoutTrashed()
                    ->where('guard_name', config('auth.defaults.guard'))
                    ->where(fn ($query) => $query->where('team_id', currentOrganizationId())->orWhereNull('team_id'))
                    ->ignore($role instanceof Role ? $role->id : null),
            ],
            'description'      => ['nullable', 'string', 'max:255'],
            'is_active'        => ['boolean'],
            'permission_ids'   => ['array', 'max:500', new BulkExists('iam_permissions', extraConditions: ['guard_name' => config('auth.defaults.guard')])],
            'permission_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
