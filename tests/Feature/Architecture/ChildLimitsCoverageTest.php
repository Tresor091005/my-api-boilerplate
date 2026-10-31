<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Traits\HasAdjacencyList;

it('declares a limit, or null on purpose, for every relation from a parent to its children', function (): void {
    setPermissionsTeamId('00000000-0000-0000-0000-000000000000');
    $undeclared = [];

    foreach (getAllModels() as $modelClass) {
        $model = new ReflectionClass($modelClass);

        if ($model->isAbstract() || !$model->isSubclassOf(Model::class)) {
            continue;
        }

        // A tree package declares children() without a return type.
        $packageRelations = in_array(HasAdjacencyList::class, class_uses_recursive($modelClass), true) ? ['children'] : [];

        foreach ($model->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $returns = (string) $method->getReturnType();
            $isDeclaredHere = str_starts_with((string) $method->getFileName(), base_path('app-modules'))
                && preg_match('/\\\\(HasMany|MorphMany|BelongsToMany|MorphToMany)$/', $returns) === 1;

            if ($method->getNumberOfParameters() > 0 || (!$isDeclaredHere && !in_array($method->getName(), $packageRelations, true))) {
                continue;
            }

            /** @var Relation<Model, Model, mixed> $relation */
            $relation = $model->newInstance()->{$method->getName()}();
            [$table, $foreignKey] = match (true) {
                $relation instanceof BelongsToMany => [$relation->getTable(), $relation->getForeignPivotKeyName()],
                $relation instanceof HasOneOrMany  => [$relation->getRelated()->getTable(), $relation->getForeignKeyName()],
                default                            => throw new LogicException("{$modelClass}::{$method->getName()}() is not a parent to children relation."),
            };

            if (!array_key_exists($foreignKey, config("child-limits.{$table}", []))) {
                $undeclared[] = "{$table}.{$foreignKey} ({$model->getShortName()}::{$method->getName()}())";
            }
        }
    }

    expect(array_values(array_unique($undeclared)))->toBe([], 'Declare these keys in config/child-limits.php, with a number or null.');
});
