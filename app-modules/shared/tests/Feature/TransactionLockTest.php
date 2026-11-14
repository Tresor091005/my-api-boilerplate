<?php

declare(strict_types=1);

namespace Lahatre\Shared\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Lahatre\Shared\Support\TransactionLock;
use LogicException;

afterEach(function (): void {
    DB::purge('lock_probe');
});

function transactionLockIsFree(string $key): bool
{
    config(['database.connections.lock_probe' => config('database.connections.'.config('database.default'))]);

    return (bool) DB::connection('lock_probe')->scalar('SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0))', [$key]);
}

function transactionLockIsFreeForSharing(string $key): bool
{
    config(['database.connections.lock_probe' => config('database.connections.'.config('database.default'))]);

    return (bool) DB::connection('lock_probe')->scalar('SELECT pg_try_advisory_xact_lock_shared(hashtextextended(?, 0))', [$key]);
}

it('holds a key for another connection until the transaction ends, and only that key', function (): void {
    DB::transaction(function (): void {
        TransactionLock::acquire('test:b', 'test:a');

        expect(transactionLockIsFree('test:a'))->toBeFalse()
            ->and(transactionLockIsFree('test:b'))->toBeFalse()
            ->and(transactionLockIsFree('test:c'))->toBeTrue();
    });

    expect(transactionLockIsFree('test:a'))->toBeTrue();
});

it('gives the transaction back its own lock wait once locked', function (): void {
    DB::transaction(function (): void {
        DB::select("SELECT set_config('lock_timeout', '2s', true)");

        TransactionLock::acquire('test:a');

        expect(DB::scalar("SELECT current_setting('lock_timeout')"))->toBe('2s');
    });
});

it('refuses to lock outside a transaction, where the lock would end at once', function (): void {
    expect(fn () => TransactionLock::acquire('test:a'))->toThrow(LogicException::class);
});

it('lets other shared holders in but keeps exclusive ones out until the transaction ends', function (): void {
    DB::transaction(function (): void {
        TransactionLock::acquireShared('test:b', 'test:a', 'test:a');

        expect(transactionLockIsFreeForSharing('test:a'))->toBeTrue()
            ->and(transactionLockIsFree('test:a'))->toBeFalse()
            ->and(transactionLockIsFree('test:b'))->toBeFalse()
            ->and(transactionLockIsFree('test:c'))->toBeTrue();
    });

    expect(transactionLockIsFree('test:a'))->toBeTrue();
});

it('keeps shared holders out while the key is held exclusively', function (): void {
    DB::transaction(function (): void {
        TransactionLock::acquire('test:a');

        expect(transactionLockIsFreeForSharing('test:a'))->toBeFalse();
    });
});

it('fails an exclusive request after the lock wait while another transaction shares the key', function (): void {
    config(['database.connections.lock_probe' => config('database.connections.'.config('database.default'))]);
    $probe = DB::connection('lock_probe');
    $probe->beginTransaction();
    $probe->select('SELECT pg_advisory_xact_lock_shared(hashtextextended(?, 0))', ['test:a']);

    try {
        DB::transaction(fn () => TransactionLock::acquire('test:a'));
        $this->fail('The exclusive lock should not have been granted.');
    } catch (QueryException $exception) {
        // 55P03: lock_not_available, raised when lock_timeout runs out.
        expect($exception->getCode())->toBe('55P03');
    } finally {
        $probe->rollBack();
    }
});

it('fails a shared request after the lock wait while another transaction holds the key exclusively', function (): void {
    config(['database.connections.lock_probe' => config('database.connections.'.config('database.default'))]);
    $probe = DB::connection('lock_probe');
    $probe->beginTransaction();
    $probe->select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['test:a']);
    $startedAt = microtime(true);

    try {
        DB::transaction(fn () => TransactionLock::acquireShared('test:a'));
        $this->fail('The shared lock should not have been granted.');
    } catch (QueryException $exception) {
        // 55P03: lock_not_available, raised when lock_timeout runs out.
        expect($exception->getCode())->toBe('55P03')
            ->and(microtime(true) - $startedAt)->toBeGreaterThan(4.5)->toBeLessThan(10.0);
    } finally {
        $probe->rollBack();
    }
});

it('gives the transaction back its own lock wait once shared locks are taken', function (): void {
    DB::transaction(function (): void {
        DB::select("SELECT set_config('lock_timeout', '2s', true)");

        TransactionLock::acquireShared('test:a');

        expect(DB::scalar("SELECT current_setting('lock_timeout')"))->toBe('2s');
    });
});

it('refuses to take a shared lock outside a transaction', function (): void {
    expect(fn () => TransactionLock::acquireShared('test:a'))->toThrow(LogicException::class);
});
