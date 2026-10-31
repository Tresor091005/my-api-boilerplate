<?php

declare(strict_types=1);

namespace Lahatre\Shared\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * PostgreSQL advisory locks held until the current transaction ends. Only code
 * asking for the same key waits; rows and tables are not locked.
 */
final class TransactionLock
{
    /** How long to wait for a key before failing, so stuck holders do not pile up waiting requests. */
    public const string WAIT = '5s';

    /**
     * Keys are taken in sorted order, so two transactions locking the same keys cannot deadlock.
     *
     * @throws LogicException when no transaction is open: the lock would be released at once
     */
    public static function acquire(string ...$keys): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('A transaction lock needs an open transaction.');
        }

        $keys = array_values(array_unique($keys));
        sort($keys);
        $previousWait = (string) DB::scalar("SELECT current_setting('lock_timeout')");

        DB::select("SELECT set_config('lock_timeout', ?, true)", [self::WAIT]);

        foreach ($keys as $key) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$key]);
        }

        DB::select("SELECT set_config('lock_timeout', ?, true)", [$previousWait]);
    }
}
