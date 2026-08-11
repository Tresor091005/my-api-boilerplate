<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchMemberRoleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'member_role_id' => ['required', 'string', 'uuid'],
        ];
    }
}
