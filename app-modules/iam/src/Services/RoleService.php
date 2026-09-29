<?php

declare(strict_types=1);

namespace Lahatre\Iam\Services;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Lahatre\Iam\Data\RoleData;
use Lahatre\Iam\Data\RoleFilterData;
use Lahatre\Iam\Exceptions\RoleException;
use Lahatre\Iam\Models\MemberRole;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function paginate(RoleFilterData $filters): CursorPaginator
    {
        $organizationId = currentOrganizationId();
        $query = Role::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->where(function ($query) use ($organizationId): void {
                $query->where('team_id', $organizationId)
                    ->orWhere(fn ($query) => $query->whereNull('team_id')->where('is_builtin', true));
            });

        return stableCursorPaginate(applyResponseContextToQuery($query), $filters);
    }

    public function retrieve(Role $role): Role
    {
        $this->assertVisible($role);

        return $role->load(responseRelationsToLoad());
    }

    public function create(RoleData $data): Role
    {
        $organizationId = currentOrganizationId();
        $name = required($data->name);
        $permissionIds = required($data->permissionIds);

        try {
            $role = DB::transaction(function () use ($organizationId, $name, $data, $permissionIds): Role {
                $this->assertNameAvailable($name, $organizationId);
                $permissions = $this->resolvePermissions($permissionIds);
                $role = Role::query()->create([
                    'team_id'     => $organizationId,
                    'name'        => $name,
                    'description' => required($data->description),
                    'guard_name'  => config('auth.defaults.guard'),
                    'is_builtin'  => false,
                ]);
                $role->syncPermissions($permissions);

                return $role;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw RoleException::nameTaken($name);
            }

            throw $exception;
        }

        return $role->load(responseRelationsToLoad());
    }

    public function update(Role $role, RoleData $data): Role
    {
        $organizationId = currentOrganizationId();

        try {
            $updated = DB::transaction(function () use ($role, $data, $organizationId): Role {
                $lockedRole = Role::query()
                    ->where('team_id', $organizationId)
                    ->where('guard_name', config('auth.defaults.guard'))
                    ->whereKey($role->id)
                    ->lockForUpdate()
                    ->first();
                $this->assertMutable($lockedRole, $role->id, $organizationId);

                if (!$data->name instanceof MissingValue) {
                    $this->assertNameAvailable($data->name, $organizationId, $lockedRole->id);
                }
                $lockedRole->fill(withoutMissing([
                    'name'        => $data->name,
                    'description' => $data->description,
                ]));
                if (!$data->permissionIds instanceof MissingValue) {
                    $permissions = $this->resolvePermissions($data->permissionIds);
                    $lockedRole->syncPermissions($permissions);
                }

                $lockedRole->save();

                return $lockedRole;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw RoleException::nameTaken(
                    $data->name instanceof MissingValue ? $role->name : $data->name,
                );
            }

            throw $exception;
        }

        return $updated->load(responseRelationsToLoad());
    }

    /** Soft-delete a tenant role without an active member assignment. */
    public function delete(Role $role): void
    {
        $organizationId = currentOrganizationId();

        DB::transaction(function () use ($role, $organizationId): void {
            $lockedRole = Role::query()
                ->where('team_id', $organizationId)
                ->where('guard_name', config('auth.defaults.guard'))
                ->whereKey($role->id)
                ->lockForUpdate()
                ->first();
            $this->assertMutable($lockedRole, $role->id, $organizationId);

            if (MemberRole::query()
                ->where('organization_id', $organizationId)
                ->where('role_id', $lockedRole->id)
                ->exists()) {
                throw RoleException::assigned($role->id);
            }

            Role::query()
                ->where('team_id', $organizationId)
                ->whereKey($lockedRole->id)
                ->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assertVisible(Role $role): void
    {
        if ($role->guard_name !== config('auth.defaults.guard')
            || ($role->team_id !== currentOrganizationId() && !($role->team_id === null && $role->is_builtin))) {
            throw RoleException::unavailable($role->id);
        }
    }

    private function assertMutable(?Role $role, string $roleId, string $organizationId): void
    {
        if (!$role || $role->team_id !== $organizationId) {
            throw RoleException::unavailable($roleId);
        }
        if ($role->is_builtin) {
            throw RoleException::systemRole($roleId);
        }
    }

    private function assertNameAvailable(string $name, string $organizationId, ?string $exceptRoleId = null): void
    {
        $exists = Role::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->where('name', $name)
            ->where(function ($query) use ($organizationId): void {
                $query->where('team_id', $organizationId)->orWhereNull('team_id');
            })
            ->when($exceptRoleId, fn ($query) => $query->whereKeyNot($exceptRoleId))
            ->exists();

        if ($exists) {
            throw RoleException::nameTaken($name);
        }
    }

    /**
     * @param  list<string>  $permissionIds
     * @return Collection<int, Permission>
     */
    private function resolvePermissions(array $permissionIds): Collection
    {
        $uniqueIds = array_values(array_unique($permissionIds));
        $permissions = Permission::query()
            ->where('guard_name', config('auth.defaults.guard'))
            ->whereIn('id', $uniqueIds)
            ->get();

        if ($permissions->count() !== count($uniqueIds)) {
            throw RoleException::permissionsUnavailable();
        }

        return $permissions;
    }
}
