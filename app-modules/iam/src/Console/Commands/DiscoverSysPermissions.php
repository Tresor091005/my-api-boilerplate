<?php

declare(strict_types=1);

namespace Lahatre\Iam\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Lahatre\Shared\Registries\MorphMapRegistry;
use Lahatre\Shared\Support\ModelFinder;
use Spatie\Permission\PermissionRegistrar;

class DiscoverSysPermissions extends Command
{
    protected $signature = 'permissions:discover';

    protected $description = 'Discover models from all modules and create CRUD permissions and default roles.';

    public function handle(): int
    {
        $this->info(__('iam::console.discovery.starting'));

        // Reset the permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = config('auth.defaults.guard');
        $baseActions = ['list', 'retrieve', 'create', 'update', 'delete'];
        $additionalActions = config('iam.system_permissions.additional_actions', []);
        $skippedModels = config('iam.system_permissions.skip_models', []);
        $morphMapRegistry = app(MorphMapRegistry::class);
        $unregisteredModels = [];
        $this->info(__('iam::console.discovery.scanning', ['path' => 'configured model namespaces']));

        foreach (ModelFinder::getAllModels() as $class) {
            $modelName = $morphMapRegistry->getAlias($class);

            if ($modelName === null) {
                $unregisteredModels[] = $class;

                continue;
            }

            if (in_array($modelName, $skippedModels, true)) {
                $this->line(__('iam::console.discovery.skipped_model', ['model' => $modelName]));

                continue;
            }

            $this->line(__('iam::console.discovery.discovered_model', ['class' => $class, 'model' => $modelName]));

            $actions = array_values(array_unique([
                ...$baseActions,
                ...($additionalActions[$modelName] ?? []),
            ]));

            foreach ($actions as $action) {
                $permissionName = "{$modelName}.{$action}";
                Permission::updateOrCreate(
                    [
                        'name'       => $permissionName,
                        'guard_name' => $guardName,
                    ],
                    [
                        'title'       => __('iam::console.permissions.title', ['action' => ucfirst($action), 'model' => $modelName]),
                        'description' => __('iam::console.permissions.description', ['action' => $action, 'model' => $modelName]),
                    ]
                );
                $this->line(__('iam::console.discovery.created_permission', ['name' => $permissionName]));
            }
        }

        if ($unregisteredModels !== []) {
            $this->warn(__('iam::console.discovery.skipped_models_summary', [
                'count'   => count($unregisteredModels),
                'classes' => implode(', ', $unregisteredModels),
            ]));
        }

        $this->info(__('iam::console.discovery.completed_syncing'));

        // Create Administrator role and assign all permissions
        $adminRole = Role::updateOrCreate(
            [
                'name'       => SysRole::Administrator->value,
                'guard_name' => $guardName,
            ],
            [
                'is_builtin'  => true,
                'description' => __('iam::console.roles.administrator.description'),
            ]
        );
        $allPermissions = Permission::where('guard_name', $guardName)->get();
        $adminRole->syncPermissions($allPermissions);
        $this->line(__('iam::console.discovery.synced_administrator'));

        // Create a Readonly role with basic list+retrieve permissions
        $readOnlyRole = Role::updateOrCreate(
            [
                'name'       => SysRole::Readonly->value,
                'guard_name' => $guardName,
            ],
            [
                'is_builtin'  => true,
                'description' => __('iam::console.roles.read_only.description'),
            ]
        );
        $readPermissions = Permission::where('guard_name', $guardName)
            ->where(function ($query): void {
                $query->where('name', 'like', '%.list')
                    ->orWhere('name', 'like', '%.retrieve');
            })->get();
        $readOnlyRole->syncPermissions($readPermissions);
        $this->line(__('iam::console.discovery.synced_read_only'));

        // Reset the permission cache again
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info(__('iam::console.discovery.success'));

        return self::SUCCESS;
    }
}
