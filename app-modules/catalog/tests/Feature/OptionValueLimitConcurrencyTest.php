<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\Option;
use Lahatre\Catalog\Services\Option\TransactionalOptionService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;
use Lahatre\Shared\Exceptions\ChildLimitException;

uses(DatabaseMigrations::class, InteractsWithCatalogTenantContext::class);

/**
 * Runs $work in a forked process with its own database connection and writes what it returns to $resultFile.
 *
 * @param  Closure(): string  $work
 */
function forkOptionValueWork(Closure $work, string $resultFile): int
{
    $pid = pcntl_fork();

    if ($pid === -1) {
        throw new RuntimeException('Unable to fork the option value process.');
    }

    if ($pid === 0) {
        try {
            DB::purge();
            file_put_contents($resultFile, $work());
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($resultFile, $exception::class.': '.$exception->getMessage());
            exit(1);
        }
    }

    return $pid;
}

it('lets only one of two concurrent requests take the last places of an option', function (): void {
    $this->initializeCatalogTenantContext();
    $max = (int) config('child-limits.catalog_option_values.option_id');
    $option = Option::factory()->create(['organization_id' => $this->organizationId, 'name' => 'Size']);
    $now = now();
    DB::table('catalog_option_values')->insert(array_map(fn (int $index): array => [
        'id'              => (string) Str::uuid7(),
        'organization_id' => $this->organizationId,
        'option_id'       => $option->id,
        'value'           => "v{$index}",
        'created_at'      => $now,
        'updated_at'      => $now,
    ], range(1, $max - 2)));
    $directory = storage_path('framework/testing/option-value-limit-'.Str::uuid7());
    File::ensureDirectoryExists($directory);
    $signal = fn (string $name): int|false => file_put_contents("{$directory}/{$name}", '1');
    $waitFor = function (string $name) use ($directory): void {
        $deadline = microtime(true) + 10;

        while (!file_exists("{$directory}/{$name}")) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException("Timed out waiting for {$name}.");
            }

            usleep(10_000);
        }
    };
    $addValues = fn (array $values): mixed => app(TransactionalOptionService::class)
        ->createMissingValues(Option::query()->findOrFail($option->id), $values);

    DB::disconnect();
    $pids = [
        forkOptionValueWork(function () use ($addValues, $signal, $waitFor): string {
            DB::transaction(function () use ($addValues, $signal, $waitFor): void {
                $addValues(['a1', 'a2']);
                $signal('first-added');
                $waitFor('second-started');
                usleep(300_000);
            });

            return 'added';
        }, "{$directory}/first"),
        forkOptionValueWork(function () use ($addValues, $signal, $waitFor): string {
            $waitFor('first-added');
            $signal('second-started');

            try {
                DB::transaction(fn (): mixed => $addValues(['b1', 'b2']));

                return 'added';
            } catch (QueryException $exception) {
                return ChildLimitException::fromQueryException($exception) === null ? $exception->getMessage() : 'refused';
            }
        }, "{$directory}/second"),
    ];

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    DB::reconnect();
    $results = [file_get_contents("{$directory}/first"), file_get_contents("{$directory}/second")];
    File::deleteDirectory($directory);

    expect($results)->toBe(['added', 'refused'])
        ->and(DB::table('catalog_option_values')->where('option_id', $option->id)->whereNull('deleted_at')->count())->toBe($max);
});
