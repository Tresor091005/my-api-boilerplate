<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Testing;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Test helper that catches an endpoint running one more database query for every row it returns.
 */
trait AssertsBoundedQueries
{
    /**
     * Checks that $request runs as many queries with $large rows as with $small rows.
     * $seed must create the number of rows it is given; $request calls the endpoint.
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
     * The SQL of every query $callback runs. A first unmeasured call warms up caches.
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
