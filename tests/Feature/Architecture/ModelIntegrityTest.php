<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('verifies that all Eloquent models have correct and explicit casts for all database columns', function (): void {
    $models = getAllModels();
    $ignoredModels = config('model-integrity.ignored_models', []);
    $failures = [];

    foreach ($models as $modelClass) {
        if (in_array($modelClass, $ignoredModels, true)) {
            continue;
        }

        $model = new $modelClass();
        $table = $model->getTable();

        if (!Schema::hasTable($table)) {
            $failures[] = "[{$modelClass}] Table '{$table}' missing.";

            continue;
        }

        $columns = Schema::getColumns($table);
        $modelCasts = $model->getCasts();

        foreach ($columns as $column) {
            $name = $column['name'];
            $dbType = strtolower((string) $column['type_name']);
            $currentCast = $modelCasts[$name] ?? null;

            // 1. Check if cast exists
            if (!$currentCast) {
                $failures[] = "[{$modelClass}] Column '{$name}' (Type: {$dbType}) is missing a cast.";

                continue;
            }

            // 2. Semantic Validation
            $expectedCast = match (true) {
                $dbType === 'uuid'                                                      => 'string',
                $dbType === 'bool' || $dbType === 'boolean'                             => 'boolean',
                str_contains($dbType, 'timestamp') || str_contains($dbType, 'datetime') => 'immutable_datetime',
                str_contains($dbType, 'int')                                            => 'integer',
                str_contains($dbType, 'json')                                           => ['array', 'collection', 'json'],
                str_contains($dbType, 'decimal') || str_contains($dbType, 'numeric')    => ['decimal', 'float', 'real'],
                $name === 'password'                                                    => ['string', 'hashed'],
                $dbType === 'varchar'                                                   => 'string',
                default                                                                 => 'string',
            };

            // Handle multiple allowed casts
            $allowedCasts = is_array($expectedCast) ? $expectedCast : [$expectedCast];

            // Check for Enums (if cast is a class name)
            $isEnum = class_exists($currentCast) && enum_exists($currentCast);

            if (!$isEnum && !in_array($currentCast, $allowedCasts, true)) {
                // Special case for decimal with precision (e.g. decimal:2)
                if (in_array('decimal', $allowedCasts) && Str::startsWith($currentCast, 'decimal:')) {
                    continue;
                }

                $expectedDisplay = is_array($expectedCast) ? implode(' OR ', $expectedCast) : $expectedCast;
                $failures[] = "[{$modelClass}] Column '{$name}' has WRONG cast. \n      Actual: '{$currentCast}' \n      Expected: '{$expectedDisplay}' (DB Type: {$dbType})";
            }
        }

        // 3. Extra/Dead casts check
        $dbColumnNames = collect($columns)->pluck('name')->toArray();
        foreach ($modelCasts as $castColumn => $castType) {
            if (!in_array($castColumn, $dbColumnNames, true)) {
                $failures[] = "[{$modelClass}] Dead cast found for non-existent column: '{$castColumn}'";
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Model Integrity Failures:\n\n".implode("\n\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('verifies that all Eloquent models use the HasFactory trait', function (): void {
    $models = getAllModels();
    $ignoredModels = config('model-integrity.ignored_models', []);
    $failures = [];

    foreach ($models as $modelClass) {
        if (in_array($modelClass, $ignoredModels, true)) {
            continue;
        }

        $traits = class_uses_recursive($modelClass);

        if (!in_array(HasFactory::class, $traits, true)) {
            $failures[] = "[{$modelClass}] is missing the HasFactory trait.";
        }
    }

    if ($failures !== []) {
        $this->fail("Missing HasFactory Trait Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('verifies SoftDeletes trait consistency with database columns', function (): void {
    $models = getAllModels();
    $ignoredModels = config('model-integrity.ignored_models', []);
    $failures = [];

    foreach ($models as $modelClass) {
        if (in_array($modelClass, $ignoredModels, true)) {
            continue;
        }

        $model = new $modelClass();
        $table = $model->getTable();

        if (!Schema::hasTable($table)) {
            continue;
        }

        $traits = class_uses_recursive($modelClass);
        $usesSoftDeletes = in_array(SoftDeletes::class, $traits, true);
        $hasDeletedAtColumn = Schema::hasColumn($table, 'deleted_at');

        if ($usesSoftDeletes && !$hasDeletedAtColumn) {
            $failures[] = "[{$modelClass}] uses SoftDeletes trait but table '{$table}' is missing 'deleted_at' column.";
        }

        if (!$usesSoftDeletes && $hasDeletedAtColumn) {
            $failures[] = "[{$modelClass}] table '{$table}' has 'deleted_at' column but model is missing SoftDeletes trait.";
        }
    }

    if ($failures !== []) {
        $this->fail("SoftDeletes Consistency Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('verifies that all models with UUID primary keys use the HasUuids trait', function (): void {
    $models = getAllModels();
    $ignoredModels = config('model-integrity.ignored_models', []);
    $failures = [];

    foreach ($models as $modelClass) {
        if (in_array($modelClass, $ignoredModels, true)) {
            continue;
        }

        $model = new $modelClass();
        $table = $model->getTable();

        if (!Schema::hasTable($table)) {
            continue;
        }

        // Check the database type of the 'id' column
        $columns = Schema::getColumns($table);
        $idColumn = collect($columns)->first(fn ($col): bool => $col['name'] === 'id');

        if ($idColumn && strtolower((string) $idColumn['type_name']) === 'uuid') {
            $traits = class_uses_recursive($modelClass);

            // Laravel's HasUuids trait
            if (!in_array(HasUuids::class, $traits, true)) {
                $failures[] = "[{$modelClass}] has a UUID primary key but is missing the HasUuids trait (check if it uses SharedTraits).";
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Missing HasUuids Trait Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures all models use $fillable instead of $guarded', function (): void {
    $models = getAllModels();
    $ignoredModels = config('model-integrity.ignored_models', []);
    $failures = [];

    foreach ($models as $modelClass) {
        if (in_array($modelClass, $ignoredModels, true)) {
            continue;
        }

        $reflection = new ReflectionClass($modelClass);
        $instance = $reflection->newInstanceWithoutConstructor();

        // 1. Check if $fillable is explicitly defined in the model class (not inherited as empty)
        $hasExplicitFillable = $reflection->hasProperty('fillable') &&
                               $reflection->getProperty('fillable')->getDeclaringClass()->getName() === $modelClass;

        $fillable = $reflection->getProperty('fillable');
        $fillableValue = $fillable->getValue($instance);

        if (!$hasExplicitFillable || empty($fillableValue)) {
            $failures[] = "[{$modelClass}] is missing an explicit and non-empty \$fillable array.";
        }

        // 2. Check if $guarded is defined by the user (it should not be, we prefer $fillable)
        $hasExplicitGuarded = $reflection->hasProperty('guarded') &&
                              $reflection->getProperty('guarded')->getDeclaringClass()->getName() === $modelClass;

        if ($hasExplicitGuarded) {
            $guarded = $reflection->getProperty('guarded');
            $guardedValue = $guarded->getValue($instance);

            if (!empty($guardedValue)) {
                $failures[] = "[{$modelClass}] has an explicit \$guarded array. Please remove it and use \$fillable only.";
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Mass Assignment Policy Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures all models use modern Attribute-based accessors and mutators', function (): void {
    $models = getAllModels();
    $ignoredModels = config('model-integrity.ignored_models', []);
    $failures = [];

    foreach ($models as $modelClass) {
        if (in_array($modelClass, $ignoredModels, true)) {
            continue;
        }

        $reflection = new ReflectionClass($modelClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);
        $modelFileName = $reflection->getFileName();

        foreach ($methods as $method) {
            // Only check methods defined in the actual model file, not inherited or from traits in other files
            if ($method->getFileName() !== $modelFileName) {
                continue;
            }

            // Check for old naming convention: getXXXAttribute or setXXXAttribute
            if (Str::startsWith($method->getName(), 'get') && Str::endsWith($method->getName(), 'Attribute') && $method->getName() !== 'getAttribute') {
                $failures[] = "[{$modelClass}] uses old accessor style: '{$method->getName()}'. Use 'Illuminate\Database\Eloquent\Casts\Attribute' instead.";
            }

            if (Str::startsWith($method->getName(), 'set') && Str::endsWith($method->getName(), 'Attribute') && $method->getName() !== 'setAttribute') {
                $failures[] = "[{$modelClass}] uses old mutator style: '{$method->getName()}'. Use 'Illuminate\Database\Eloquent\Casts\Attribute' instead.";
            }
        }
    }

    if ($failures !== []) {
        $this->fail("Modern Attribute Style Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('ensures all models have an explicit protected $table property', function (): void {
    $models = getAllModels();
    $ignoredModels = config('model-integrity.ignored_models', []);
    $failures = [];

    foreach ($models as $modelClass) {
        if (in_array($modelClass, $ignoredModels, true)) {
            continue;
        }

        $reflection = new ReflectionClass($modelClass);

        // 1. Check if $table is explicitly defined in the model class
        $hasExplicitTable = $reflection->hasProperty('table') &&
                            $reflection->getProperty('table')->getDeclaringClass()->getName() === $modelClass;

        if (!$hasExplicitTable) {
            $failures[] = "[{$modelClass}] is missing an explicit protected \$table property.";
        }
    }

    if ($failures !== []) {
        $this->fail("Explicit Table Name Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});

it('verifies that each model has an existing factory class', function (): void {
    $models = getAllModels();
    $ignoredModels = config('model-integrity.ignored_models', []);
    $modulesNamespace = config('app-modules.modules_namespace', 'Lahatre');
    $failures = [];

    foreach ($models as $modelClass) {
        if (in_array($modelClass, $ignoredModels, true)) {
            continue;
        }

        // Handle Laravel's custom factory resolution if any,
        // but here we manually check based on our project structure
        $factoryClass = null;

        if (Str::startsWith($modelClass, 'App\\Models\\')) {
            // Core models: App\Models\User\User -> Database\Factories\User\UserFactory
            $relativeName = Str::after($modelClass, 'App\\Models\\');
            $factoryClass = 'Database\\Factories\\'.$relativeName.'Factory';
        } elseif (Str::startsWith($modelClass, $modulesNamespace.'\\')) {
            // Module models: Lahatre\Catalog\Models\Product -> Lahatre\Catalog\Database\Factories\ProductFactory
            // Our modules use Internachi/Modular convention usually
            $parts = explode('\\', $modelClass);
            $moduleName = $parts[1]; // e.g. Catalog
            $modelName = end($parts);
            $factoryClass = $modulesNamespace.'\\'.$moduleName.'\\Database\\Factories\\'.$modelName.'Factory';
        }

        if ($factoryClass && !class_exists($factoryClass)) {
            $failures[] = "[{$modelClass}] has no corresponding factory class '{$factoryClass}'.";
        }
    }

    if ($failures !== []) {
        $this->fail("Missing Factory Class Failures:\n\n".implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});
