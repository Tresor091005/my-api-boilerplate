<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lahatre\Billing\Exceptions\FeatureUnavailableException;
use Lahatre\Catalog\Data\StockLocationData;
use Lahatre\Catalog\Models\StockLocation;
use Lahatre\Catalog\Services\StockLocationService;
use Lahatre\Catalog\Tests\Concerns\InteractsWithCatalogTenantContext;

uses(DatabaseMigrations::class, InteractsWithCatalogTenantContext::class);

/**
 * Runs $work in a forked process with its own database connection and writes what it returns to $resultFile.
 *
 * @param  Closure(): string  $work
 */
function forkStockLocationWork(Closure $work, string $resultFile): int
{
    $pid = pcntl_fork();

    if ($pid === -1) {
        throw new RuntimeException('Unable to fork the stock location process.');
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

it('lets only one of two concurrent requests take the last stock location of the plan', function (): void {
    $this->initializeCatalogTenantContext();
    subscribeOrganizationTo($this->organizationId, ['catalog.stock_locations' => ['allowance' => 1]]);
    $directory = storage_path('framework/testing/stock-location-limit-'.Str::uuid7());
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
    $create = fn (string $name): StockLocation => app(StockLocationService::class)
        ->create(StockLocationData::fromArray(['name' => $name, 'is_active' => true]));

    DB::disconnect();
    $pids = [
        forkStockLocationWork(function () use ($create, $signal, $waitFor): string {
            DB::transaction(function () use ($create, $signal, $waitFor): void {
                $create('First Warehouse');
                $signal('first-created');
                $waitFor('second-started');
                usleep(300_000);
            });

            return 'created';
        }, "{$directory}/first"),
        forkStockLocationWork(function () use ($create, $signal, $waitFor): string {
            $waitFor('first-created');
            $signal('second-started');

            try {
                $create('Second Warehouse');

                return 'created';
            } catch (FeatureUnavailableException) {
                return 'refused';
            }
        }, "{$directory}/second"),
    ];

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    DB::reconnect();
    $results = [file_get_contents("{$directory}/first"), file_get_contents("{$directory}/second")];
    File::deleteDirectory($directory);

    expect($results)->toBe(['created', 'refused'])
        ->and(StockLocation::query()->where('organization_id', $this->organizationId)->count())->toBe(1);
});
