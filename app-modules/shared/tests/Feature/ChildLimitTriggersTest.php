<?php

declare(strict_types=1);

namespace Lahatre\Shared\Tests\Feature;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Lahatre\Shared\Exceptions\ChildLimitException;
use Lahatre\Shared\Support\ChildLimitTriggers;
use LogicException;

uses(RefreshDatabase::class, WithConsoleEvents::class);

beforeEach(function (): void {
    DB::statement('CREATE TABLE test_parents (id uuid PRIMARY KEY)');
    DB::statement('CREATE TABLE test_children (id uuid PRIMARY KEY, parent_id uuid REFERENCES test_parents (id), other_id uuid, organization_id uuid, group_id uuid, is_pinned boolean NOT NULL DEFAULT false, deleted_at timestamp NULL)');
    DB::statement('CREATE INDEX test_children_parent_id_index ON test_children (parent_id) WHERE deleted_at IS NULL');
    config(['child-limits' => ['test_children' => ['parent_id' => 2]]]);
    app(ChildLimitTriggers::class)->sync();
});

function childLimitParent(): string
{
    $id = (string) Str::uuid7();
    DB::table('test_parents')->insert(['id' => $id]);

    return $id;
}

/** @return list<string> */
function addChildren(string $parentId, int $count): array
{
    $ids = array_map(fn (): string => (string) Str::uuid7(), range(1, $count));
    DB::transaction(fn (): bool => DB::table('test_children')->insert(array_map(fn (string $id): array => ['id' => $id, 'parent_id' => $parentId], $ids)));

    return $ids;
}

/** The trigger names, without the hash of their definition. */
function childLimitTriggers(): array
{
    return array_map(fn (object $row): string => preg_replace('/_[0-9a-f]{8}_/', '_', $row->tgname), DB::select(
        "SELECT tgname FROM pg_trigger WHERE tgrelid = 'test_children'::regclass AND NOT tgisinternal ORDER BY tgname"
    ));
}

it('refuses a child beyond the limit, added one by one, in bulk, restored or moved', function (): void {
    $full = childLimitParent();
    $other = childLimitParent();
    [$first] = addChildren($full, 2);
    [$moved] = addChildren($other, 1);
    $exception = null;

    try {
        addChildren($full, 1);
    } catch (QueryException $caught) {
        $exception = ChildLimitException::fromQueryException($caught);
    }

    expect($exception?->getMessage())->toBe(__('shared::exceptions.child_limit.reached', ['max' => 2]))
        ->and($exception?->context())->toBe(['children' => 'test_children.parent_id', 'max' => 2])
        ->and(fn (): array => addChildren(childLimitParent(), 3))->toThrow(QueryException::class, 'child_limit:test_children.parent_id:2')
        ->and(fn (): int => DB::transaction(fn (): int => DB::table('test_children')->where('id', $moved)->update(['parent_id' => $full])))
        ->toThrow(QueryException::class, 'child_limit:test_children.parent_id:2');

    DB::table('test_children')->where('id', $first)->update(['deleted_at' => now()]);
    addChildren($full, 1);

    expect(fn (): int => DB::transaction(fn (): int => DB::table('test_children')->where('id', $first)->update(['deleted_at' => null])))
        ->toThrow(QueryException::class, 'child_limit:test_children.parent_id:2')
        ->and(DB::table('test_children')->where('parent_id', $full)->whereNull('deleted_at')->count())->toBe(2);
});

it('answers a child beyond the limit with a 422 that names the limit', function (): void {
    $parentId = childLimitParent();
    addChildren($parentId, 2);

    Route::middleware('api')->post('/_test/child-limit', fn (): array => addChildren($parentId, 1))->name('_test.child-limit');

    $this->postJson('/_test/child-limit')
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => __('shared::exceptions.child_limit.reached', ['max' => 2]),
            'errors'  => [
                'type'    => 'ChildLimitException',
                'context' => ['children' => 'test_children.parent_id', 'max' => 2],
            ],
        ]);
});

it('reports and fixes the differences with the declared limits', function (): void {
    expect(Artisan::call('child-limits:sync', ['--check' => true]))->toBe(0)
        ->and(childLimitTriggers())->toBe(['child_limit_parent_id_2_insert', 'child_limit_parent_id_2_update']);

    config(['child-limits' => ['test_children' => ['parent_id' => 3]]]);

    expect(Artisan::call('child-limits:sync', ['--check' => true]))->toBe(1)
        ->and(app(ChildLimitTriggers::class)->differences()['obsolete'])->toHaveCount(2);

    Artisan::call('child-limits:sync');

    expect(Artisan::call('child-limits:sync', ['--check' => true]))->toBe(0)
        ->and(childLimitTriggers())->toBe(['child_limit_parent_id_3_insert', 'child_limit_parent_id_3_update']);

    config(['child-limits' => ['test_children' => ['parent_id' => null]]]);
    app(ChildLimitTriggers::class)->sync();

    expect(childLimitTriggers())->toBe([]);
});

it('steps aside while migrations run, so they can drop a column a trigger watches', function (): void {
    event(new MigrationsStarted('up'));

    expect(childLimitTriggers())->toBe([]);

    DB::statement('ALTER TABLE test_children DROP COLUMN deleted_at');
    DB::statement('CREATE INDEX test_children_parent_id_index ON test_children (parent_id)');
    event(new MigrationsEnded('up'));

    expect(childLimitTriggers())->toBe(['child_limit_parent_id_2_insert', 'child_limit_parent_id_2_update']);
});

it('follows the file on every migrate, but never on a pretended one', function (): void {
    config(['child-limits' => ['test_children' => ['parent_id' => 3]]]);

    Artisan::call('migrate', ['--pretend' => true]);

    expect(childLimitTriggers())->toBe(['child_limit_parent_id_2_insert', 'child_limit_parent_id_2_update']);

    Artisan::call('migrate');

    expect(childLimitTriggers())->toBe(['child_limit_parent_id_3_insert', 'child_limit_parent_id_3_update']);
});

it('refuses a limit it could not enforce', function (array $limits, string $message): void {
    DB::statement('CREATE INDEX test_children_other_group_index ON test_children (other_id, group_id)');
    config(['child-limits' => ['test_children' => $limits]]);

    expect(fn () => app(ChildLimitTriggers::class)->sync())->toThrow(LogicException::class, $message);
})->with([
    'unknown column'                              => [['missing_id' => 2], 'child-limits.test_children.missing_id: no such column.'],
    'maximum below one'                           => [['parent_id' => 0], 'child-limits.test_children.parent_id: the maximum must be an integer of at least 1, or null for no limit.'],
    'unindexed column'                            => [['is_pinned' => 2], 'child-limits.test_children.is_pinned: no index can serve the check, or each check would scan the table.'],
    'index led by a column children do not share' => [['group_id' => 2], 'child-limits.test_children.group_id: no index can serve the check, or each check would scan the table.'],
    'invalid condition'                           => [['parent_id' => ['max' => 2, 'where' => 'missing_column IS NULL']], 'child-limits.test_children.parent_id: the condition is not valid SQL on test_children.'],
]);

it('counts only the children meeting the condition, and checks a child that starts meeting it', function (): void {
    config(['child-limits' => ['test_children' => ['parent_id' => ['max' => 1, 'where' => 'is_pinned']]]]);
    app(ChildLimitTriggers::class)->sync();
    $parentId = childLimitParent();
    $pin = fn (string $id): int => DB::transaction(fn (): int => DB::table('test_children')->where('id', $id)->update(['is_pinned' => true]));

    [$first, $second] = addChildren($parentId, 2);
    $pin($first);

    expect(fn (): int => $pin($second))->toThrow(QueryException::class, 'child_limit:test_children.parent_id:1')
        ->and(fn (): bool => DB::transaction(fn (): bool => DB::table('test_children')->insert(['id' => (string) Str::uuid7(), 'parent_id' => $parentId, 'is_pinned' => true])))
        ->toThrow(QueryException::class, 'child_limit:test_children.parent_id:1');
});

it('checks through an existing index that starts with the organization', function (): void {
    DB::statement('CREATE INDEX test_children_organization_other_index ON test_children (organization_id, other_id) WHERE deleted_at IS NULL');
    config(['child-limits' => ['test_children' => ['other_id' => 2]]]);
    app(ChildLimitTriggers::class)->sync();
    $organizationId = (string) Str::uuid7();
    $otherId = (string) Str::uuid7();
    $add = fn (): bool => DB::transaction(fn (): bool => DB::table('test_children')->insert(['id' => (string) Str::uuid7(), 'organization_id' => $organizationId, 'other_id' => $otherId]));

    $add();
    $add();

    expect($add)->toThrow(QueryException::class, 'child_limit:test_children.other_id:2');
});
