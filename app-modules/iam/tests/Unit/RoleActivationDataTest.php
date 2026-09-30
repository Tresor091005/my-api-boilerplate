<?php

declare(strict_types=1);

use Lahatre\Iam\Data\MemberRoleCreateData;
use Lahatre\Iam\Data\MemberRoleUpdateData;
use Lahatre\Iam\Data\OrganizationMemberUpdateData;
use Lahatre\Iam\Data\RoleData;
use Lahatre\Shared\Data\MissingValue;

it('defaults new role and assignment activation to true', function (): void {
    expect(RoleData::fromArray(['name' => 'Editor', 'permission_ids' => []])->isActive)->toBeTrue()
        ->and(MemberRoleCreateData::fromArray(['role_ids' => []])->isActive)->toBeTrue();
});

it('preserves missing activation on partial role updates', function (): void {
    $data = RoleData::fromArray([], ['name', 'description', 'permission_ids', 'is_active']);
    expect($data->isActive)->toBe(MissingValue::Instance);
});

it('maps validated boolean activation values including false and zero', function (bool|int|string $value, bool $expected): void {
    expect(RoleData::fromArray(['name' => 'Editor', 'permission_ids' => [], 'is_active' => $value])->isActive)->toBe($expected)
        ->and(MemberRoleCreateData::fromArray(['role_ids' => [], 'is_active' => $value])->isActive)->toBe($expected)
        ->and(MemberRoleUpdateData::fromArray(['member_role_ids' => [], 'is_active' => $value])->isActive)->toBe($expected)
        ->and(OrganizationMemberUpdateData::fromArray(['is_active' => $value])->isActive)->toBe($expected);
})->with([[true, true], [false, false], [1, true], [0, false], ['1', true], ['0', false]]);
