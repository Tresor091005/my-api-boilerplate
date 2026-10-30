<?php

declare(strict_types=1);

namespace Lahatre\Tax\Tests\Concerns;

use Illuminate\Support\Str;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Organization\Models\Organization;
use Lahatre\Tax\Data\TaxableLineData;
use Lahatre\Tax\Data\TaxData;
use Lahatre\Tax\Data\TaxProfileData;
use Lahatre\Tax\Models\Tax;
use Lahatre\Tax\Models\TaxProfile;
use Lahatre\Tax\Services\TaxProfileService;
use Lahatre\Tax\Services\TaxService;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tenant context for tax tests; the organization functional currency is XOF (no decimals).
 */
trait InteractsWithTaxTenant
{
    public const array TAX_PERMISSIONS = [
        'tax_tax.list',
        'tax_tax.retrieve',
        'tax_tax.create',
        'tax_tax.update',
        'tax_tax.delete',
        'tax_profile.list',
        'tax_profile.retrieve',
        'tax_profile.create',
        'tax_profile.update',
        'tax_profile.delete',
    ];

    /** @return list<string> */
    protected function taxPermissions(): array
    {
        return self::TAX_PERMISSIONS;
    }

    protected function setUpTaxTenant(): void
    {
        $this->organization = Organization::factory()->create();
        setPermissionsTeamId($this->organization->id);
    }

    /** @param list<string> $permissions */
    protected function authenticateTaxMember(array $permissions = self::TAX_PERMISSIONS): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = User::factory()->create();
        $member = OrganizationMember::create([
            'user_id'         => $user->id,
            'organization_id' => $this->organization->id,
        ]);
        $role = Role::query()->firstOrCreate([
            'name'       => 'tax-member-'.Str::random(8),
            'guard_name' => 'sanctum',
            'team_id'    => $this->organization->id,
        ]);
        $memberRole = MemberRole::create([
            'organization_id' => $this->organization->id,
            'member_id'       => $member->id,
            'role_id'         => $role->id,
        ]);

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $memberRole->givePermissionTo($permissions);
        $token = $user->createToken('tax-token');
        $token->accessToken->update([
            'metadata' => [
                'organization_id' => $this->organization->id,
                'member_id'       => $member->id,
                'member_role_id'  => $memberRole->id,
                'role_id'         => $role->id,
            ],
        ]);
        $this->withToken($token->plainTextToken);
    }

    /** @param array<string, mixed> $definition */
    public function createTax(array $definition): Tax
    {
        return app(TaxService::class)->create(TaxData::fromArray([
            'code'             => 'TAX-'.fake()->unique()->numerify('####'),
            'name'             => 'Tax',
            'calculation_type' => 'percentage',
            ...$definition,
        ]));
    }

    public function createPercentageTax(string $rate, array $definition = []): Tax
    {
        return $this->createTax([
            'brackets' => [['base_min' => '0', 'base_max' => null, 'value' => $rate]],
            ...$definition,
        ]);
    }

    /** @param list<array<string, mixed>> $lines */
    public function createProfile(array $lines, array $definition = []): TaxProfile
    {
        return app(TaxProfileService::class)->create(TaxProfileData::fromArray([
            'code'  => 'PROFILE-'.fake()->unique()->numerify('####'),
            'name'  => 'Profile',
            'lines' => $lines,
            ...$definition,
        ]));
    }

    /** @param array<string, mixed> $overrides */
    public function taxableLine(?string $taxProfileId, string $baseAmount, array $overrides = []): TaxableLineData
    {
        return TaxableLineData::fromArray([
            'tax_profile_id' => $taxProfileId,
            'unit_group_id'  => 'no-unit-group',
            'base_amount'    => $baseAmount,
            'currency_code'  => 'XOF',
            'quantity'       => '1',
            ...$overrides,
        ]);
    }
}
