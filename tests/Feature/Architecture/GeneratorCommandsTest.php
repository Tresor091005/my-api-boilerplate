<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Product;
use Symfony\Component\Console\Command\Command;

it('generates module-aware service, policy, resource, and collection classes', function (): void {
    $suffix = Str::studly(Str::random(10));
    $serviceName = "GeneratorContractService{$suffix}";
    $policyName = "GeneratorContractPolicy{$suffix}";
    $resourceName = "GeneratorContract{$suffix}Resource";

    $generatedFiles = [
        base_path("app-modules/shared/src/Services/{$serviceName}.php"),
        base_path("app-modules/catalog/src/Policies/{$policyName}.php"),
        base_path("app-modules/catalog/src/Http/Resources/{$resourceName}.php"),
        base_path("app-modules/catalog/src/Http/Resources/GeneratorContract{$suffix}Collection.php"),
    ];

    try {
        expect(Artisan::call('make:service', [
            'name'             => $serviceName,
            '--module'         => 'shared',
            '--force'          => true,
            '--no-interaction' => true,
        ]))->toBe(0);

        expect(Artisan::call('make:policy', [
            'name'             => $policyName,
            '--module'         => 'catalog',
            '--model'          => Product::class,
            '--force'          => true,
            '--no-interaction' => true,
        ]))->toBe(0);

        expect(Artisan::call('make:api-resource', [
            'name'             => $resourceName,
            '--module'         => 'catalog',
            '--model'          => Product::class,
            '--force'          => true,
            '--no-interaction' => true,
        ]))->toBe(0);

        $service = File::get($generatedFiles[0]);
        $policy = File::get($generatedFiles[1]);
        $resource = File::get($generatedFiles[2]);
        $collection = File::get($generatedFiles[3]);

        expect($service)
            ->toContain('namespace Lahatre\\Shared\\Services;')
            ->toContain("final class {$serviceName}");

        expect($policy)
            ->toContain('use Lahatre\\Catalog\\Models\\Product;')
            ->toContain("class {$policyName} extends BasePolicy")
            ->toContain("return \$this->canModel('list', Product::class);");

        expect($resource)
            ->toContain('@mixin \\Lahatre\\Catalog\\Models\\Product')
            ->toContain("'handle' => \$this->handle,");

        expect(str_contains($resource, "'organization_id'"))->toBeFalse();

        expect($collection)
            ->toContain('use Lahatre\\Shared\\Http\\Resources\\BaseCollection;')
            ->toContain("public \$collects = {$resourceName}::class;");

        expect(str_contains($collection, "use Lahatre\\Catalog\\Http\\Resources\\{$resourceName};"))->toBeFalse();

        expect(Artisan::call('make:api-resource', [
            'name'             => $resourceName,
            '--module'         => 'catalog',
            '--model'          => Product::class,
            '--no-interaction' => true,
        ]))->toBe(Command::FAILURE);
    } finally {
        foreach ($generatedFiles as $generatedFile) {
            File::delete($generatedFile);
        }
    }
});

it('generates the standard module file matrix with project conventions', function (): void {
    $suffix = Str::studly(Str::random(10));
    $modelName = "GeneratorMatrix{$suffix}";
    $files = [
        base_path("app-modules/catalog/src/Models/{$modelName}.php"),
        base_path("app-modules/catalog/database/factories/{$modelName}Factory.php"),
        base_path("app-modules/catalog/database/seeders/{$modelName}Seeder.php"),
        base_path("app-modules/catalog/src/Http/Requests/Store{$modelName}Request.php"),
        base_path("app-modules/catalog/src/Http/Requests/Update{$modelName}Request.php"),
        base_path("app-modules/catalog/src/Http/Controllers/{$modelName}Controller.php"),
        base_path("app-modules/catalog/src/Policies/{$modelName}Policy.php"),
        base_path("app-modules/catalog/tests/Feature/{$modelName}Test.php"),
        base_path("app-modules/catalog/tests/Feature/{$modelName}ControllerTest.php"),
        base_path("app-modules/catalog/src/Support/{$modelName}.php"),
        base_path("app-modules/catalog/src/Enums/{$modelName}.php"),
        base_path("app-modules/catalog/src/Contracts/{$modelName}.php"),
        base_path("app-modules/catalog/src/Jobs/Middleware/{$modelName}.php"),
        base_path("app-modules/catalog/src/Models/Scopes/{$modelName}.php"),
        base_path("app-modules/catalog/src/Traits/{$modelName}.php"),
        base_path("app-modules/catalog/resources/views/{$modelName}.blade.php"),
    ];

    try {
        expect(Artisan::call('make:model', [
            'name'             => $modelName,
            '--module'         => 'catalog',
            '--all'            => true,
            '--pest'           => true,
            '--force'          => true,
            '--no-interaction' => true,
        ]))->toBe(0);

        $simpleGenerators = [
            ['make:class', "Support/{$modelName}"],
            ['make:enum', $modelName],
            ['make:interface', $modelName],
            ['make:job-middleware', $modelName],
            ['make:scope', $modelName],
            ['make:trait', $modelName],
            ['make:view', $modelName],
        ];

        foreach ($simpleGenerators as [$command, $name]) {
            expect(Artisan::call($command, [
                'name'             => $name,
                '--module'         => 'catalog',
                '--force'          => true,
                '--no-interaction' => true,
            ]))->toBe(0);
        }

        $migrationFiles = File::glob(base_path('app-modules/catalog/database/migrations/*_create_catalog_'.Str::snake(Str::pluralStudly($modelName)).'_table.php'));
        $migration = File::get($migrationFiles[0]);
        $model = File::get($files[0]);
        $factory = File::get($files[1]);
        $seeder = File::get($files[2]);
        $request = File::get($files[3]);
        $controller = File::get($files[5]);
        $policy = File::get($files[6]);

        expect($migrationFiles)->toHaveCount(1)
            ->and($model)->toContain("protected \$table = 'catalog_".Str::snake(Str::pluralStudly($modelName))."';")
            ->and($migration)->toContain("\$table->uuid('id')->primary();")
            ->and($factory)->toContain('declare(strict_types=1);')
            ->and($seeder)->toContain('declare(strict_types=1);')
            ->and($request)->toContain('class Store'.$modelName.'Request extends FormRequest')
            ->and($controller)->toContain('use Lahatre\\Catalog\\Http\\Requests\\Store'.$modelName.'Request;')
            ->toContain('use Illuminate\\Http\\JsonResponse;')
            ->toContain(': JsonResponse')
            ->and($policy)->toContain('extends BasePolicy')
            ->and(File::get($files[7]))->toContain('declare(strict_types=1);')
            ->and(File::get($files[9]))->toContain('namespace Lahatre\\Catalog\\Support;')
            ->and(File::get($files[10]))->toContain('namespace Lahatre\\Catalog\\Enums;')
            ->and(File::get($files[11]))->toContain('namespace Lahatre\\Catalog\\Contracts;')
            ->and(File::get($files[12]))->toContain('namespace Lahatre\\Catalog\\Jobs\\Middleware;')
            ->and(File::get($files[13]))->toContain('namespace Lahatre\\Catalog\\Models\\Scopes;')
            ->and(File::get($files[14]))->toContain('namespace Lahatre\\Catalog\\Traits;')
            ->and(File::exists($files[15]))->toBeTrue();
    } finally {
        foreach ($files as $file) {
            File::delete($file);
        }

        foreach (File::glob(base_path('app-modules/catalog/database/migrations/*_create_catalog_'.Str::snake(Str::pluralStudly($modelName)).'_table.php')) as $migrationFile) {
            File::delete($migrationFile);
        }
    }
});
