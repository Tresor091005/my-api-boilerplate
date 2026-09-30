<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Shared\Rules\BulkExists;

class InvitationRolesRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'role_ids' => ['required', 'array', 'min:1', 'max:100', new BulkExists(
                'iam_roles', handleSoftDelete: true, extraConditions: [
                    'team_id'    => currentOrganizationId(),
                    'is_builtin' => false,
                    'guard_name' => config('auth.defaults.guard'),
                ],
            )],
            'role_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
