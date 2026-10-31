<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Lahatre\Catalog\Database\Seeders\BundleSeeder;
use Lahatre\Catalog\Database\Seeders\CategorySeeder;
use Lahatre\Catalog\Database\Seeders\InventoryDemoSeeder;
use Lahatre\Catalog\Database\Seeders\OptionSeeder;
use Lahatre\Catalog\Database\Seeders\ProductSeeder;
use Lahatre\Catalog\Database\Seeders\StockLocationSeeder;
use Lahatre\Customer\Database\Seeders\CustomerSeeder;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Iam\Models\Role;
use Lahatre\Iam\Models\User;
use Lahatre\Iam\Services\OrganizationOnboardingService;
use Lahatre\Organization\Data\OrganizationData;
use Lahatre\Organization\Models\Organization;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@lahatre.com'],
            [
                'first_name' => 'Test',
                'last_name'  => 'User',
            ]
        );

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Artisan::call('permissions:discover');

        $organization = Organization::query()->where('name', 'kouri')->first();

        if ($organization === null) {
            app(OrganizationOnboardingService::class)->createForUser($user, OrganizationData::fromArray(
                ['name' => 'kouri', 'currency_code' => 'XOF', 'timezone' => 'Africa/Porto-Novo'],
                $user->id,
            ));
            $organization = Organization::query()->where('name', 'kouri')->firstOrFail();
        }

        $member = OrganizationMember::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->firstOrFail();

        setPermissionsTeamId($organization->id);

        $this->call([
            CategorySeeder::class,
            OptionSeeder::class,
            ProductSeeder::class,
            BundleSeeder::class,
            StockLocationSeeder::class,
            InventoryDemoSeeder::class,
            CustomerSeeder::class,
        ]);

        foreach (SysRole::cases() as $sysRole) {
            $this->assignRole(
                $member,
                Role::whereNull('team_id')->whereName($sysRole->value)->first()
            );
        }
    }

    public function assignRole(OrganizationMember $member, Role $role)
    {
        $memberRole = MemberRole::firstOrCreate([
            'organization_id' => $member->organization_id,
            'member_id'       => $member->id,
            'role_id'         => $role->id,
        ]);

        $memberRole->syncRoles($role);
    }
}
