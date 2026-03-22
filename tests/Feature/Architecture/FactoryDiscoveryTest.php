<?php

declare(strict_types=1);

use App\Models\Career\Job;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Catalog\Models\Category;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

it('resolves factories automatically for models', function (string $modelClass): void {
    $factory = Factory::factoryForModel($modelClass);

    expect($factory)->not->toBeNull()
        ->and($factory)->toBeInstanceOf(Factory::class);
})->with('models');

it('resolves models automatically from factories', function (string $modelClass): void {
    $factory = Factory::factoryForModel($modelClass);
    $resolvedModel = $factory->modelName();

    expect($resolvedModel)->toBe($modelClass);
})->with('models');

dataset('models', [
    'User'      => User::class,
    'Job'       => Job::class,
    'Currency'  => Currency::class,
    'Unit'      => Unit::class,
    'UnitGroup' => UnitGroup::class,
    'Category'  => Category::class,
]);
