<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Testing;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Response tests: the number of queries a response issues must not depend on the number of rows it
 * renders. A query per row (a computedBy closure querying, a relation read without being declared in
 * production) shows up as a count growing with the rows.
 */
trait AssertsBoundedQueries
{
    /**
     * Seeds $small rows and measures $request, seeds up to $large rows and measures again: both
     * measurements must issue the same number of queries. Each measurement follows an unmeasured
     * warm-up call, so per-request caches (permissions, settings) do not skew the count.
     *
     * @param  Closure(int $count, int $alreadySeeded): void  $seed
     * @param  Closure(): mixed  $request
     */
    protected function assertBoundedQueries(Closure $seed, Closure $request, int $small = 2, int $large = 6): void
    {
        $seed($small, 0);
        $smallQueries = $this->measureQueries($request);

        $seed($large - $small, $small);
        $largeQueries = $this->measureQueries($request);

        $this->assertSame(count($smallQueries), count($largeQueries), sprintf(
            "The query count depends on the number of rows (%d rows: %d queries, %d rows: %d queries).\n%d rows:\n%s\n%d rows:\n%s",
            $small, count($smallQueries), $large, count($largeQueries),
            $small, implode("\n", $smallQueries),
            $large, implode("\n", $largeQueries),
        ));
    }

    /**
     * SQL of every query $callback issues, after an unmeasured warm-up call.
     *
     * @return array<int, string>
     */
    protected function measureQueries(Closure $callback): array
    {
        $callback();

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();
        } finally {
            $queries = array_map(fn (array $query): string => $query['query'], DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return $queries;
    }
}
