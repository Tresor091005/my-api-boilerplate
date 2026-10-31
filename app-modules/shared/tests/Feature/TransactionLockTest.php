<?php

declare(strict_types=1);

namespace Lahatre\Shared\Tests\Feature;

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
