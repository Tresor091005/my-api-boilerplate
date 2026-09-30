<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Shared\Rules\BulkExists;

class MemberRoleDeleteRequest extends FormRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $member = $this->route('organizationMember');
        assert($member instanceof OrganizationMember);

        return [
            'member_role_ids' => ['required', 'array', 'list', 'min:1', 'max:100', new BulkExists(
                'iam_member_roles', handleSoftDelete: true, extraConditions: [
                    'organization_id' => currentOrganizationId(),
                    'member_id'       => $member->id,
                ],
            )],
            'member_role_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
