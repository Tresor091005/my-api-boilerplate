<?php

declare(strict_types=1);

namespace Lahatre\Shared\Tests\Feature;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lahatre\Shared\Support\GenerationalCache\GenerationalCache;
use RuntimeException;

beforeEach(function (): void {
    $this->store = new Repository(new ArrayStore);
    $this->resolved = [];
    $this->read = function (GenerationalCache $cache, string $group, array $ids): array {
        return $cache->many(
            $group,
            collect($ids),
            3600,
            function (Collection $missingIds): Collection {
                $this->resolved[] = $missingIds->all();

                return $missingIds->mapWithKeys(fn (string $id): array => [$id => strtoupper($id)]);
            },
            fn (string $entry): array  => ['value' => $entry],
            fn (mixed $stored): string => $stored['value'],
        )->all();
    };
});

it('resolves only the entries missing from the cache', function (): void {
    $first = ($this->read)(new GenerationalCache($this->store), 'group', ['a', 'b']);
    $second = ($this->read)(new GenerationalCache($this->store), 'group', ['a', 'b', 'c']);

    expect($first)->toBe(['a' => 'A', 'b' => 'B'])
        ->and($second)->toEqualCanonicalizing(['a' => 'A', 'b' => 'B', 'c' => 'C'])
        ->and($this->resolved)->toBe([['a', 'b'], ['c']]);
});

it('invalidates every entry of a group with a new generation, and only that group', function (): void {
    ($this->read)(new GenerationalCache($this->store), 'first', ['a']);
    ($this->read)(new GenerationalCache($this->store), 'second', ['a']);

    new GenerationalCache($this->store)->invalidateAfterCommit('first');
    ($this->read)(new GenerationalCache($this->store), 'first', ['a']);
    ($this->read)(new GenerationalCache($this->store), 'second', ['a']);

    expect($this->resolved)->toBe([['a'], ['a'], ['a']]);
});

it('invalidates only once the transaction commits, and never on rollback', function (): void {
    ($this->read)(new GenerationalCache($this->store), 'group', ['a']);

    try {
        DB::transaction(function (): void {
            new GenerationalCache($this->store)->invalidateAfterCommit('group');

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
    }

    ($this->read)(new GenerationalCache($this->store), 'group', ['a']);

    DB::transaction(function (): void {
        new GenerationalCache($this->store)->invalidateAfterCommit('group');
        ($this->read)(new GenerationalCache($this->store), 'group', ['a']);
    });

    ($this->read)(new GenerationalCache($this->store), 'group', ['a']);

    expect($this->resolved)->toBe([['a'], ['a']]);
});

it('keeps the entries of a request in memory', function (): void {
    $cache = new GenerationalCache($this->store);
    ($this->read)($cache, 'group', ['a']);
    $this->store->forget('group:'.$this->store->get('group:generation').':a');

    expect(($this->read)($cache, 'group', ['a']))->toBe(['a' => 'A'])
        ->and($this->resolved)->toBe([['a']]);
});

it('leaves out the ids nobody can resolve, without caching them', function (): void {
    $cache = new GenerationalCache($this->store);
    $entries = $cache->many('group', collect(['known', 'unknown']), 3600, fn (Collection $ids): Collection => collect(['known' => 'K']), fn (string $entry): string => $entry, fn (mixed $stored): string => $stored);

    expect($entries->all())->toBe(['known' => 'K'])
        ->and(count($this->store->getStore()->all()))->toBe(2);
});
