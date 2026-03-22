<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

if (!function_exists('ensure_transaction')) {
    /**
     * Ensure that the given callback is executed within a transaction.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    function ensure_transaction(Closure $callback, int $attempts = 1): mixed
    {
        if (DB::transactionLevel() === 0) {
            return DB::transaction($callback, $attempts);
        }

        // $attempts is intentionally ignored here — we're inside an existing transaction
        return $callback();
    }
}

if (!function_exists('getDefaultTeamId')) {
    function getDefaultTeamId(): string
    {
        // TODO: SHOULD BE REMOVED when authContext is complete
        return '019c5b9b-697d-72e5-ab19-b2186fc49375';
    }
}
