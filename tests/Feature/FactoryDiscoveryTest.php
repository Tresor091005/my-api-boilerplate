<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Factories\Factory;
use Lahatre\Master\Models\Currency;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;
use Lahatre\Catalog\Models\Category;

it('resolves factories automatically for models', function (string $modelClass) {
    $factory = Factory::factoryForModel($modelClass);

    expect($factory)->not->toBeNull()
        ->and($factory)->toBeInstanceOf(Factory::class);
})->with('models');

it('resolves models automatically from factories', function (string $modelClass) {
    $factory = Factory::factoryForModel($modelClass);
    $resolvedModel = $factory->modelName();

    expect($resolvedModel)->toBe($modelClass);
})->with('models');

dataset('models', [
    'Currency'   => Currency::class,
    'Unit'       => Unit::class,
    'UnitGroup'  => UnitGroup::class,
    'Category'   => Category::class,
]);
