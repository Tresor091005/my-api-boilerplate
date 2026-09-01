<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lahatre\Iam\Models\User;
use Lahatre\Shared\Registries\MorphMapRegistry;

/**
 * Clear the morph map cache file.
 */
function clearCache(): void
{
    $cachePath = App::bootstrapPath('cache/morph-map.php');
    if (File::exists($cachePath)) {
        File::delete($cachePath);
    }
}

beforeEach(function (): void {
    config(['app.env' => 'testing']);
    clearCache();
});

afterEach(function (): void {
    config(['app.env' => 'testing']);
    clearCache();
});

it('registers models via auto-discovery when cache is missing', function (): void {
    Relation::morphMap([], false);

    $registry = new MorphMapRegistry;
    $registry->discover();

    $map = Relation::morphMap();

    expect($map)->not->toBeEmpty()
        ->and($map)->toHaveKey('iam_user')
        ->and($map['iam_user'])->toBe(User::class);
});

it('generates aliases from singular model table names', function (): void {
    Relation::morphMap([], false);

    $registry = new MorphMapRegistry;
    $registry->discover();

    foreach ($registry->getMap() as $alias => $class) {
        expect($alias)->toBe(Str::singular((new $class)->getTable()));
    }
});

it('ignores a cache file outside production and runs discovery', function (): void {
    config(['app.env' => 'local']);

    $cachePath = App::bootstrapPath('cache/morph-map.php');
    $customMap = ['custom_user' => User::class];

    File::put($cachePath, '<?php return '.var_export($customMap, true).';');

    Relation::morphMap([], false);

    $registry = new MorphMapRegistry;
    $registry->discover();

    $map = Relation::morphMap();

    expect($map)->not->toBe($customMap)
        ->and($map)->toHaveKey('iam_user')
        ->and($map)->not->toHaveKey('custom_user');
});

it('loads from cache file when available in production', function (): void {
    config(['app.env' => 'production']);

    $cachePath = App::bootstrapPath('cache/morph-map.php');
    $customMap = ['custom_user' => User::class];

    File::put($cachePath, '<?php return '.var_export($customMap, true).';');
    Relation::morphMap([], false);

    $registry = new MorphMapRegistry;
    $registry->discover();

    $map = Relation::morphMap();

    expect($map)->toBe($customMap)
        ->and($map)->toHaveKey('custom_user')
        ->and($map)->not->toHaveKey('iam_user');
});

it('can clear the cache file', function (): void {
    $cachePath = App::bootstrapPath('cache/morph-map.php');
    File::put($cachePath, '<?php return [];');

    $registry = new MorphMapRegistry;
    $registry->discover();
    $registry->clear();

    expect(File::exists($cachePath))->toBeFalse();
});

it('can create the cache file via the cache method', function (): void {
    $cachePath = App::bootstrapPath('cache/morph-map.php');

    $registry = new MorphMapRegistry;
    $registry->discover();
    $registry->cache();

    expect(File::exists($cachePath))->toBeTrue();
    $cachedMap = require $cachePath;
    expect($cachedMap)->toHaveKey('iam_user');
});

it('boots the shared provider with separate optimization commands', function (): void {
    currentTestCase()->artisan('list')->assertSuccessful();
});
