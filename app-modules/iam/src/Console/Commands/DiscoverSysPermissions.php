<?php

declare(strict_types=1);

namespace Lahatre\Iam\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lahatre\Iam\Enums\SysRole;
use Lahatre\Iam\Models\Permission;
use Lahatre\Iam\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DiscoverSysPermissions extends Command
{
    protected $signature = 'permissions:discover';

    protected $description = 'Discover models from all modules and create CRUD permissions and default roles.';

    public function handle(): int
    {
        $this->info('Starting permission discovery...');

        // Reset the permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = config('auth.defaults.guard');
        $actions = ['list', 'retrieve', 'create', 'update', 'delete'];
        $modulesPath = config('app-modules.modules_directory', 'app-modules');

        $this->info("Scanning for models in: {$modulesPath}/*/src/Models");

        $modelFiles = File::glob(base_path($modulesPath).'/*/src/Models/*.php');

        foreach ($modelFiles as $file) {
            $class = $this->getClassFromFile($file);

            if (!$class || !class_exists($class) || !is_subclass_of($class, Model::class)) {
                continue;
            }

            $modelName = Str::plural(Str::snake(class_basename($class)));

            $this->line("Discovered model: {$class} -> {$modelName}");

            foreach ($actions as $action) {
                $permissionName = "{$modelName}.{$action}";
                Permission::updateOrCreate(
                    [
                        'name'       => $permissionName,
                        'guard_name' => $guardName,
                    ],
                    [
                        'title'       => ucfirst($action).' '.$modelName,
                        'description' => "Allow to {$action} {$modelName}",
                    ]
                );
                $this->line("  ✔ Created permission: {$permissionName}");
            }
        }

        $this->info('Model permissions discovery completed. Syncing roles...');

        // Create Administrator role and assign all permissions
        $adminRole = Role::updateOrCreate(
            [
                'name'       => SysRole::Administrator->value,
                'guard_name' => $guardName,
            ],
            [
                'is_builtin'  => true,
                'description' => 'Administrator with all permissions.',
            ]
        );
        $allPermissions = Permission::where('guard_name', $guardName)->get();
        $adminRole->syncPermissions($allPermissions);
        $this->line('✔ Synced Administrator role.');

        // Create a Default role with basic read-only permissions
        $defaultRole = Role::updateOrCreate(
            [
                'name'       => SysRole::Default->value,
                'guard_name' => $guardName,
            ],
            [
                'is_builtin'  => true,
                'description' => 'Default role with basic access.',
            ]
        );
        $readPermissions = Permission::where('guard_name', $guardName)
            ->where(function ($query): void {
                $query->where('name', 'like', '%.list')
                    ->orWhere('name', 'like', '%.retrieve');
            })->get();
        $defaultRole->syncPermissions($readPermissions);
        $this->line('✔ Synced Default role.');

        // Reset the permission cache again
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->info('Permission discovery and role synchronization completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Get the full class name from a file path.
     */
    private function getClassFromFile(string $path): ?string
    {
        $content = file_get_contents($path);

        $namespace = null;
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        $class = null;
        if (preg_match('/class\s+([^\s]+)/', $content, $matches)) {
            $class = $matches[1];
        }

        if ($namespace && $class) {
            return $namespace.'\\'.$class;
        }

        return null;
    }
}
