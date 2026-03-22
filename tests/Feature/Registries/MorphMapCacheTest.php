<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
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
    clearCache();
});

afterEach(function (): void {
    clearCache();
});

it('registers models via auto-discovery when cache is missing', function (): void {
    // Clear relation map manually to ensure a fresh start
    Relation::morphMap([], false);

    // Instantiate registry (this should trigger auto-discovery)
    new MorphMapRegistry();

    $map = Relation::morphMap();

    expect($map)->not->toBeEmpty()
        ->and($map)->toHaveKey('user')
        ->and($map['user'])->toBe(\App\Models\User\User::class);
});

it('loads from cache file when available and skips discovery', function (): void {
    $cachePath = App::bootstrapPath('cache/morph-map.php');
    $customMap = ['custom_user' => 'App\\Models\\User\\User'];

    File::put($cachePath, '<?php return ' . var_export($customMap, true) . ';');

    // Clear relation map
    Relation::morphMap([], false);

    // This should load from our custom cache file
    new MorphMapRegistry();

    $map = Relation::morphMap();

    expect($map)->toBe($customMap)
        ->and($map)->toHaveKey('custom_user')
        ->and($map)->not->toHaveKey('user'); // Auto-discovery should have been skipped
});

it('can clear the cache file', function (): void {
    $cachePath = App::bootstrapPath('cache/morph-map.php');
    File::put($cachePath, '<?php return [];');

    $registry = new MorphMapRegistry();
    $registry->clear();

    expect(File::exists($cachePath))->toBeFalse();
});

it('can create the cache file via the cache method', function (): void {
    $cachePath = App::bootstrapPath('cache/morph-map.php');

    $registry = new MorphMapRegistry();
    $registry->cache();

    expect(File::exists($cachePath))->toBeTrue();
    $cachedMap = require $cachePath;
    expect($cachedMap)->toHaveKey('user');
});
